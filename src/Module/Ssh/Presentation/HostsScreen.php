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
use LightManager\Presentation\Ui\PointerRow;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Spis hostów — jedyny ekran modułu sesji zdalnej (krok 48; **spis przestał być
 * jego własnością w kroku 60**).
 *
 * Ekran pokazuje odtąd **wpisy książki adresowej widziane oczami tego modułu**
 * (rozdział `ssh`) wraz z tym, czego książka nie wie: z kim stoi sesja.
 * Dopisywanie, zmiana i usuwanie **zeszły z niego do książki** i nie ma tu po
 * nich klawisza zastępczego. Powód jest konstrukcyjny, nie estetyczny: ekran
 * umie otworzyć **okno nakładane**, a nie inny ekran (`ScreenOutcome`), więc
 * „otwórz książkę" musiałoby dołożyć rdzeniowi drogę dla jednego wołającego.
 * Książkę otwiera globalny `Ctrl`+`W`, który stoi w stopce tak samo, jak
 * skróty pozostałych modułów — a zdanie w pustym spisie mówi o tym wprost.
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

    /**
     * Szerokość kolumny sposobu uwierzytelnienia — mieści „klucz z pliku".
     *
     * **Czternaście, nie trzynaście**, i różnica jest widoczna wyłącznie
     * w klatce: napis ma trzynaście znaków, więc w kolumnie tej samej
     * szerokości tabela ucinała go do „klucz z pli…". Ta sama usterka, co
     * „gniazdo lo…" w kroku 58, i znaleziona tą samą drogą — obejrzeniem
     * ekranu pod XTermem, bo żaden test nie mierzy szerokości liter.
     */
    private const AUTH_COLUMN = 14;

    /** Najkrótsza sensowna szerokość kolumny nazwy i adresu. */
    private const NAME_MINIMUM = 8;

    private const TARGET_MINIMUM = 10;

    private int $selected = 0;

    private readonly ScrollWindow $window;

    /**
     * @param SshSession $session **wyłącznie do czynności** — odświeżenia
     *                            i rozłączenia. Odczyt idzie przez `$reader`,
     *                            bo rejestr kwerend jest jedyną drogą do danej
     *                            (krok 53, D92 nr 3)
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
     * Gdy nic nie stoi, pokazuje położenie pliku książki: ekran bez sesji ma
     * powiedzieć, skąd bierze to, co wypisuje.
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
            // Bez sesji pas mówi, **skąd bierze się spis** — bo od kroku 60 nie
            // bierze się stąd i użytkownik ma prawo wiedzieć, gdzie go poprawić.
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

        // Do łączenia bierze się wpis **pytany o jeden identyfikator**, a nie
        // ten ze spisu: spis nie niesie ścieżki klucza, bo pole jest maskowane
        // (krok 60). Bez tej jednej linii host uwierzytelniany kluczem łączyłby
        // się agentem i nikt by nie wiedział dlaczego.
        return ScreenOutcome::opens($this->flow->begin($this->reader->entry($profile->id) ?? $profile));
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
                    $profile->name,
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
