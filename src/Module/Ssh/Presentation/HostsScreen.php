<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Ssh\Application\SessionStage;
use LightManager\Module\Ssh\Application\SshSession;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Domain\Exception\InvalidHostProfileException;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostCredentials;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Presentation\Ui\AcceptsPointer;
use LightManager\Presentation\Ui\Component\Align;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\FocusHint;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\PointerRow;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Spis hostów — **czytany z książki adresowej od kroku 60**.
 *
 * Ekran zostaje tym, czym był (rozstrzygnięcie D105 nr 5), ale przestaje być
 * właścicielem spisu: wpisy przychodzą kwerendą `address-book.entries`, a
 * dopisanie i usunięcie adresu należą odtąd do książki (`Ctrl`+`W`). Zostaje
 * tu to, czego książka nie wie: **z kim stoi sesja** i **czym się przedstawiamy**.
 *
 * Spis hostów — jedyny ekran modułu sesji zdalnej (krok 48).
 *
 * **Nie dokłada ani jednego komponentu** i jest to jego drugi, cichy sprawdzian:
 * `Table` z kroku 27, `ConfirmOverlay` z 28, `PromptOverlay` z 41
 * i `ProgressOverlay` z tego samego kroku wystarczyły na funkcję, której projekt
 * dotąd nie miał w żadnej postaci. Nowy jest wyłącznie sposób ich złożenia.
 *
 * **Łańcuch okien jest tu dłuższy niż gdziekolwiek indziej w aplikacji** i to
 * jest jedyna rzecz warta uwagi w tej klasie. Połączenie z hostem nieznanym
 * przechodzi przez cztery okna z rzędu, a stos ma **jedno piętro** (D75), więc
 * każde ustępuje następnemu przez `OverlayOutcome::replace()`:
 *
 * ```
 * [hasło] → postęp (keyscan) → pytanie o odcisk → postęp (mistrz) → zamknięcie
 * ```
 *
 * Pierwsze okno odpada dla dróg bezhasłowych, dwa środkowe — dla hosta, którego
 * `~/.ssh/known_hosts` już zna. Najkrótsza droga to jedno okno postępu.
 *
 * Praca posuwa się **w `GameLoop`, nie w `draw()`** (`RunsWork`, reguła 11d
 * w brzmieniu z kroku 41), i tutaj powód jest mocniejszy niż przy plikach: nie
 * chodzi o skutki uboczne rysowania, tylko o to, że kawałek pracy trwa tyle, ile
 * trwa sieć.
 */
final class HostsScreen implements ScreenInterface, DeclaresFocus, Resettable, AcceptsPointer
{
    /** Prostokąt z ostatniego rysowania — pamięć wymagana przez `AcceptsPointer` (krok 55). */
    private ?Rect $lastBounds = null;

    public const ID = 'ssh-hosts';

    /** Szerokość kolumny stanu — najdłuższa nazwa etapu plus oddech. */
    private const STATE_COLUMN = 14;

    /** Szerokość kolumny sposobu uwierzytelnienia — mieści „klucz z pliku". */
    private const AUTH_COLUMN = 13;

    /** Najkrótsza sensowna szerokość kolumny nazwy i adresu. */
    private const NAME_MINIMUM = 8;

    private const TARGET_MINIMUM = 10;

    private int $selected = 0;

    private readonly ScrollWindow $window;

    /**
     * @param SshSession $session **wyłącznie do czynności** — dopisania wpisu,
     *                            usunięcia, odświeżenia i rozłączenia. Odczyt
     *                            idzie przez `$reader`, bo rejestr kwerend jest
     *                            jedyną drogą do danej (krok 53, D92 nr 3)
     */
    public function __construct(
        private readonly SshSession $session,
        private readonly ConnectFlow $flow,
        private readonly TranslatorPort $translator,
        private readonly SshQueries $reader,
    ) {
        $this->window = new ScrollWindow();
    }

    public function id(): string
    {
        return self::ID;
    }

    public function labelKey(): string
    {
        return 'module.' . SshSettings::ID . '.screen.hosts';
    }

    /**
     * Górny pas mówi **z kim aplikacja jest połączona** — to jest zdanie
     * z kryteriów ukończenia kroku, a nie ozdoba.
     *
     * Gdy nic nie stoi, mówi **skąd bierze spis** — od kroku 60 jest nim książka
     * adresowa, a nie własny plik modułu. Położenia dokumentu ten ekran nie
     * pokazuje i nie ma po co: to nie on nim rządzi.
     */
    public function header(): ScreenZone
    {
        $state = $this->reader->session();
        $host = $state->host;

        $text = $host !== null && $state->stage !== SessionStage::Idle
            ? $this->text('module.' . SshSettings::ID . '.header.session', [
                'stage' => $this->text($state->stage->labelKey()),
                'host' => $host->label(),
            ])
            : $this->text('module.' . SshSettings::ID . '.header.book');

        return new ScreenZone($this->labelKey(), new Label($text));
    }

    public function reset(): void
    {
        $this->selected = 0;
        $this->window->useContext(self::ID);
    }

    public function draw(Rect $bounds): array
    {
        $this->lastBounds = $bounds;
        $hosts = $this->reader->hosts();

        if ($hosts === []) {
            return (new Label($this->text('module.' . SshSettings::ID . '.empty'), '', Role::Muted))->draw($bounds);
        }

        $this->clampSelection();
        $capacity = Table::capacityOf($bounds, withHeader: true);
        $this->window->keepVisible($this->selected, count($hosts), $capacity);

        return (new Table(
            $this->columns(),
            $this->rows($hosts),
            $this->selected,
            $this->window->position(count($hosts), $capacity),
            withHeader: true,
        ))->draw($bounds);
    }

    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'module.' . SshSettings::ID . '.key.move'),
            KeyBinding::of([Key::Enter], 'module.' . SshSettings::ID . '.key.connect', 'module.' . SshSettings::ID . '.key.connect.short'),
            KeyBinding::of([Key::F4], 'module.' . SshSettings::ID . '.key.auth', 'module.' . SshSettings::ID . '.key.auth.short'),
            KeyBinding::of([Key::F5], 'module.' . SshSettings::ID . '.key.refresh', 'module.' . SshSettings::ID . '.key.refresh.short'),
        ];
    }

    /**
     * Ekran ma **jedno miejsce ogniska**, więc podpowiedź jest jedna — ale
     * deklaruje ją mimo to (krok 40), bo bez deklaracji stopka milczałaby o tym
     * ekranie w całości.
     */
    public function focus(): FocusHint
    {
        return new FocusHint('module.' . SshSettings::ID . '.focus.hosts', $this->bindings());
    }

    /**
     * Wskaźnik na liście hostów: kółko przewija, lewy przycisk stawia kursor
     * (krok 55).
     *
     * Miejsce ogniska jest tu jedno, więc kliknięcie nie ma czego przenosić —
     * i to jest cała różnica wobec ekranów z podziałem.
     */
    public function pointer(PointerEvent $event): ScreenOutcome
    {
        $bounds = $this->lastBounds;

        if ($bounds === null || !$event->hits($bounds)) {
            return ScreenOutcome::stay();
        }

        if ($event->isScroll()) {
            $this->window->scrollBy($event->scrollRows());

            return ScreenOutcome::stay();
        }

        if ($event->action !== PointerAction::Press || $event->button === PointerButton::Middle) {
            return ScreenOutcome::stay();
        }

        $row = PointerRow::of(
            $event,
            $bounds,
            $this->window->offset(),
            withHeader: true,
            total: count($this->reader->hosts()),
        );

        return $row === null ? ScreenOutcome::stay() : $this->putSelection($row);
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        return match ($key->key) {
            Key::ArrowUp => $this->select(-1),
            Key::ArrowDown => $this->select(1),
            Key::Home => $this->putSelection(0),
            Key::End => $this->putSelection(count($this->reader->hosts()) - 1),
            Key::Enter => $this->activate(),
            Key::F4 => $this->cycleAuth(),
            Key::F5 => $this->refresh(),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * `Enter`: łączy z podświetlonym albo rozłącza, gdy to z nim stoi sesja.
     *
     * Jedna sesja naraz (D87 nr 7), więc `Enter` na innym wpisie przy żywej sesji
     * **zrywa poprzednią** — i robi to port, nie ekran, bo to jego reguła.
     */
    private function activate(): ScreenOutcome
    {
        $profile = $this->underCursor();

        if ($profile === null) {
            return ScreenOutcome::stay();
        }

        $state = $this->reader->session();

        if ($state->isConnected() && $state->concerns($profile)) {
            $this->session->disconnect();

            return ScreenOutcome::stay(Message::info(
                $this->text('module.' . SshSettings::ID . '.message.disconnected', ['host' => $profile->label()]),
            ));
        }

        return ScreenOutcome::opens($this->flow->begin($profile));
    }

    /**
     * `F4`: przestawia sposób uwierzytelnienia podświetlonego wpisu.
     *
     * Klawisz istnieje dlatego, że bez niego sposób dałoby się zmienić **tylko
     * przez edycję pliku** — zakładka ustawień rządzi wyłącznie wpisami
     * **nowymi**. Spis, który użytkownik prowadzi z ekranu (cel kroku), musi
     * umieć zmienić to, co już w nim stoi.
     *
     * Przejście na „klucz z pliku" pyta o ścieżkę, bo bez niej ten sposób nie ma
     * znaczenia: `IdentityFile` bez pliku jest opcją bez wartości.
     */
    private function cycleAuth(): ScreenOutcome
    {
        $profile = $this->underCursor();

        if ($profile === null) {
            return ScreenOutcome::stay();
        }

        $next = self::nextAuth($profile->auth);

        if ($next === AuthMethod::Key) {
            return ScreenOutcome::opens(new PromptOverlay(
                'module.' . SshSettings::ID . '.prompt.key',
                ['host' => $profile->displayName()],
                $profile->keyPath ?? '',
                fn (string $path): OverlayOutcome => OverlayOutcome::close($this->reauthenticated($profile, $next, $path)),
                $this->translator,
                'module.' . SshSettings::ID . '.prompt.key.field',
            ));
        }

        return ScreenOutcome::stay($this->reauthenticated($profile, $next, null));
    }

    private function reauthenticated(HostProfile $profile, AuthMethod $auth, ?string $keyPath): Message
    {
        try {
            $changed = $profile->withAuth($auth, $keyPath);
        } catch (InvalidHostProfileException $exception) {
            return Message::error($this->text($exception->problemKey(), $exception->problemParameters()));
        }

        // Poświadczenie zapisuje **ten moduł u siebie**, a nie książka: sposób
        // uwierzytelnienia i ścieżka klucza są materiałem, którego kwerenda
        // czytana przez wszystkich nie oddaje (11w, krok 60).
        $this->session->saveCredentials($changed->id, new HostCredentials($auth, $keyPath));

        return Message::info($this->text('module.' . SshSettings::ID . '.message.auth', [
            'host' => $changed->displayName(),
            'auth' => $this->text('module.' . SshSettings::ID . '.auth.' . $auth->value),
        ]));
    }

    /** Kolejność jest kolejnością przypadków enuma — czyli malejącego bezpieczeństwa. */
    private static function nextAuth(AuthMethod $current): AuthMethod
    {
        $cases = AuthMethod::cases();
        $position = (int) array_search($current, $cases, true);

        return $cases[($position + 1) % count($cases)];
    }

    private function refresh(): ScreenOutcome
    {
        if (!$this->reader->session()->isConnected()) {
            return ScreenOutcome::stay(Message::info($this->text('module.' . SshSettings::ID . '.message.nothing')));
        }

        $this->session->refresh();

        return ScreenOutcome::stay();
    }

    /** @return list<Column> */
    private function columns(): array
    {
        return [
            Column::flexible(
                self::NAME_MINIMUM,
                label: $this->text('module.' . SshSettings::ID . '.column.name'),
            ),
            Column::flexible(
                self::TARGET_MINIMUM,
                label: $this->text('module.' . SshSettings::ID . '.column.target'),
            ),
            // Kolumna sposobu ustępuje pierwsza w wąskim oknie (`yieldOrder` 1),
            // bo stan jest ważniejszy od tego, czym się przedstawiamy.
            Column::fixed(
                self::AUTH_COLUMN,
                yieldOrder: 1,
                label: $this->text('module.' . SshSettings::ID . '.column.auth'),
            ),
            Column::fixed(
                self::STATE_COLUMN,
                yieldOrder: 2,
                align: Align::Right,
                label: $this->text('module.' . SshSettings::ID . '.column.state'),
            ),
        ];
    }

    /**
     * @param list<HostProfile> $hosts
     *
     * @return list<TableRow>
     */
    private function rows(array $hosts): array
    {
        $state = $this->reader->session();
        $rows = [];

        foreach ($hosts as $profile) {
            $stage = $state->concerns($profile) ? $state->stage : SessionStage::Idle;

            $rows[] = new TableRow(
                [
                    $profile->displayName(),
                    $profile->label(),
                    $this->text('module.' . SshSettings::ID . '.auth.' . $profile->auth->value),
                    $this->text($stage->labelKey()),
                ],
                self::roleOf($stage),
            );
        }

        return $rows;
    }

    /**
     * Kolor wiersza mówi o stanie, zanim przeczyta się kolumnę.
     *
     * Rolę `Marked` (zieleń, krok 43) bierze sesja stojąca, a nie akcent —
     * i jest to wniosek z tamtego kroku wzięty wprost: rola dobrana
     * „znaczeniowo" bez sprawdzenia palety bywa rolą bez koloru, a `Warning`
     * jest w Grafitcie tym samym kolorem co akcent.
     */
    private static function roleOf(SessionStage $stage): Role
    {
        return match ($stage) {
            SessionStage::Connected => Role::Marked,
            SessionStage::Failed => Role::Danger,
            SessionStage::Idle => Role::Text,
            default => Role::Accent,
        };
    }

    private function underCursor(): ?HostProfile
    {
        $this->clampSelection();

        return $this->reader->hosts()[$this->selected] ?? null;
    }

    private function select(int $delta): ScreenOutcome
    {
        return $this->putSelection($this->selected + $delta);
    }

    private function putSelection(int $index): ScreenOutcome
    {
        $count = count($this->reader->hosts());

        if ($count === 0) {
            $this->selected = 0;

            return ScreenOutcome::stay();
        }

        $this->selected = max(0, min($index, $count - 1));

        return ScreenOutcome::stay();
    }

    private function clampSelection(): void
    {
        $count = count($this->reader->hosts());
        $this->selected = $count === 0 ? 0 : max(0, min($this->selected, $count - 1));
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate($key, $parameters);
    }
}
