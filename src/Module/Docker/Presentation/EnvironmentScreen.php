<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\EnvironmentRow;
use LightManager\Module\Docker\Application\Environments;
use LightManager\Module\Docker\Application\TunnelStage;
use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;
use LightManager\Module\Docker\Domain\ValueObject\EnvironmentKind;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Component\Align;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Overlay\ChoiceOverlay;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Presentation\Ui\PointerRow;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Spis środowisk — czwarta postać ekranu modułu Dockera (krok 58).
 *
 * Wzorem `HostsScreen` z kroku 48: spis, dodanie, zmiana, usunięcie, wybór
 * bieżącego — i tak samo bez ani jednego nowego komponentu. Postacią ekranu,
 * a nie osobnym ekranem, bo `ScreenStack` liczy ekrany po tożsamości
 * (rozstrzygnięcie odziedziczone po krokach 49 i 51).
 *
 * **Wpis czytany od klienta pokazuje się w spisie, ale zmiany i usunięcia nie
 * przyjmuje** (D96 nr 3) — należy do cudzego narzędzia, a moduł do cudzych
 * plików nie pisze. Odmowa jest zdaniem, nie ciszą.
 *
 * **Cel tunelu rozstrzyga się tutaj, przy wyborze**: napis równy nazwie wpisu
 * książki hostów idzie kwerendą `ssh.hosts` (trzy napisy, ani jednego typu —
 * reguła 15g), każdy inny czyta się jako `[user@]host`. Moduł umie żyć bez
 * odpowiedzi: moduł Ssh wyłączony albo odrzucony znaczy drogę wprost.
 */
final class EnvironmentScreen
{
    /**
     * Szerokość kolumny rodzaju — mieści najdłuższą nazwę rodzaju.
     *
     * **Szesnaście, a nie dwanaście** (poprawka z 2026-08-18, przy pierwszym
     * obejrzeniu klatki pod XTermem): `gniazdo lokalne` ma piętnaście znaków,
     * więc dwunastoznakowa kolumna ucinała go do `gniazdo lo…` — w każdym
     * wierszu tego rodzaju, czyli w wierszu domyślnym pierwszego uruchomienia.
     * **Szesnaście, bo kolumna oddaje ostatni znak na odstęp** od sąsiadki:
     * przy piętnastu napis nadal tracił literę (sprawdzone złotą klatką, nie
     * rachunkiem). Lekcja jest ta sama, co przy roli `Marked` w kroku 43:
     * **miara dobrana z głowy, bez obejrzenia klatki, bywa miarą nietrafioną**,
     * a napisy katalogu są jedyną, którą wolno się tu kierować.
     */
    private const KIND_COLUMN = 16;

    /** Szerokość kolumny pochodzenia — mieści „klient docker". */
    private const ORIGIN_COLUMN = 14;

    /** Szerokość kolumny stanu — najdłuższe zdanie stanu plus oddech. */
    private const STATE_COLUMN = 14;

    private const NAME_MINIMUM = 8;

    private const ADDRESS_MINIMUM = 12;

    private int $selected = 0;

    private ?Rect $lastBounds = null;

    private readonly ScrollWindow $window;

    public function __construct(
        private readonly Environments $environments,
        private readonly TranslatorPort $translator,
        private readonly DockerQueries $reader,
        private readonly LoopState $state,
    ) {
        $this->window = new ScrollWindow();
        $this->window->useContext(DockerSettings::ID . ':environments');
    }

    /** Wołane przy wejściu w postać: świeży odczyt kontekstów klienta. */
    public function refresh(): void
    {
        $this->environments->refresh();
    }

    public function reset(): void
    {
        $this->selected = 0;
        $this->window->useContext(DockerSettings::ID . ':environments');
    }

    /**
     * Zdanie górnego pasa: stan tunelu, gdy bieżące środowisko go ma —
     * inaczej „demon nie odpowiada" i „tunel nie wstał" wyglądałyby
     * identycznie (plan kroku, punkt 4). Bez tunelu — gdzie leży plik książki.
     */
    public function headerText(): string
    {
        $view = $this->reader->environments();
        $tunnel = $view->tunnel;

        if ($tunnel->stage !== TunnelStage::None) {
            return $this->text('env.header.tunnel', [
                'name' => $view->current,
                'stage' => $this->text(substr($tunnel->stage->labelKey(), strlen('module.docker.'))),
            ]);
        }

        return $view->location;
    }

    /** @return list<Primitive> */
    public function draw(Rect $bounds): array
    {
        $this->lastBounds = $bounds;
        $view = $this->reader->environments();

        if ($view->count() === 0) {
            return (new Label($this->text('env.empty'), '', Role::Muted))->draw($bounds);
        }

        $this->clampSelection();
        $capacity = Table::capacityOf($bounds, withHeader: true);
        $this->window->keepVisible($this->selected, $view->count(), $capacity);

        return (new Table(
            $this->columns(),
            $this->rows(),
            $this->selected,
            $this->window->position($view->count(), $capacity),
            withHeader: true,
        ))->draw($bounds);
    }

    /** @return list<KeyBinding> */
    public function bindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short'),
            KeyBinding::of([Key::Enter], $this->key('env.key.select'), $this->key('env.key.select.short')),
            KeyBinding::ctrl('r', $this->key('env.key.refresh'), $this->key('key.refresh.short')),
            KeyBinding::of([Key::Escape], $this->key('key.back'), $this->key('key.back.short')),
        ];
    }

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
            total: $this->reader->environments()->count(),
        );

        return $row === null ? ScreenOutcome::stay() : $this->putSelection($row);
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        return match ($key->key) {
            Key::ArrowUp => $this->putSelection($this->selected - 1),
            Key::ArrowDown => $this->putSelection($this->selected + 1),
            Key::Home => $this->putSelection(0),
            Key::End => $this->putSelection($this->reader->environments()->count() - 1),
            Key::Enter => $this->activate(),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * `Enter`: wybór środowiska bieżącego.
     *
     * Tunel wstaje **na wybór** (autostartu nie ma — plan, poza zakresem)
     * i bez okna postępu: jego stan widać w górnym pasie i w kolumnie stanu,
     * a listy mówią zdaniem, dopóki nie ma z kim rozmawiać. Ponowny wybór
     * wpisu tunelowego podnosi tunel od nowa — to jest droga „spróbuj
     * jeszcze raz".
     *
     * **Wybór wpisu tunelowego pyta wpierw o sposób uwierzytelnienia** (D102
     * nr 4): klucz albo agent (odpowiedź pierwsza — `Enter` bez namysłu robi
     * to, co przed rozszerzeniem), hasło (okno maskowane, jak łączenie
     * w module Ssh) albo przerwanie (`Esc` — odpowiedź ostatnia, wedle
     * kontraktu `ChoiceOverlay`). Hasło nie jest nigdzie zapisywane i żyje
     * wyłącznie między oknem a uruchomieniem potomka. Droga „puste pole
     * hasła = klucz" była rozważana i odpadła: `PromptOverlay` na pustym polu
     * świadomie nie robi nic (krok 41) i nie ma powodu tego ruszać.
     */
    private function activate(): ScreenOutcome
    {
        $row = $this->underCursor();

        if ($row === null) {
            return ScreenOutcome::stay();
        }

        $entry = $this->environments->find($row->id === '' ? $row->name : $row->id);

        if ($entry !== null && $entry->kind === EnvironmentKind::SshTunnel) {
            [$target, $port] = $this->resolveTunnelTarget($entry);

            return ScreenOutcome::opens(new ChoiceOverlay(
                $this->key('env.prompt.auth'),
                ['target' => $target ?? $entry->target],
                [
                    'key' => $this->key('env.auth.key'),
                    'password' => $this->key('env.auth.password'),
                    'cancel' => $this->key('env.prompt.cancel'),
                ],
                fn (string $choice): OverlayOutcome => match ($choice) {
                    'key' => OverlayOutcome::close($this->selected($row, $target, $port, null)),
                    'password' => OverlayOutcome::replace($this->passwordPrompt($row, $target, $port)),
                    default => OverlayOutcome::close(),
                },
                $this->translator,
            ));
        }

        return ScreenOutcome::stay($this->selected($row, null, null, null));
    }

    private function passwordPrompt(EnvironmentRow $row, ?string $target, ?int $port): PromptOverlay
    {
        return new PromptOverlay(
            $this->key('env.prompt.tunnelPassword'),
            ['target' => $target ?? $row->name],
            '',
            fn (string $password): OverlayOutcome => OverlayOutcome::close(
                $this->selected($row, $target, $port, $password),
            ),
            $this->translator,
            $this->key('env.prompt.tunnelPassword.field'),
            masked: true,
        );
    }

    /**
     * Wspólne zakończenie wyboru — zdanie o skutku dla obu dróg.
     *
     * **Wybiera się identyfikatorem, a mówi nazwą** (krok 60): tożsamością wpisu
     * jest identyfikator, ale zdanie w pasku stanu ma powiedzieć to, co
     * użytkownik widzi w spisie. Bez tego rozdzielenia komunikat brzmiał
     * „przełączono na bbbbbbbbbbbb".
     */
    private function selected(EnvironmentRow $row, ?string $target, ?int $port, ?string $password): Message
    {
        $problem = $this->environments->select(
            $row->id === '' ? $row->name : $row->id,
            $target,
            $port,
            $password,
        );

        if ($problem !== null) {
            return Message::error($this->translator->translate($problem, ['name' => $row->name]));
        }

        return Message::info($this->text($target !== null ? 'env.switching' : 'env.switched', ['name' => $row->name]));
    }

    /**
     * Cel tunelu — **odniesienie do wpisu książki, nie nazwa** (krok 60,
     * etap 2).
     *
     * Pole `target` rozdziału `docker` jest rodzaju `entry`, więc niesie
     * identyfikator wpisu, a nie napis, za którym nikt nie umie pójść. Adres
     * bierze się stąd **jednym pytaniem o cudzy rozdział** (`address-book.entry`
     * z argumentem `ssh`) i jest to droga zamierzona, nie obejście: rozdział nie
     * jest przegrodą (D104 nr 1).
     *
     * Zysk widać przy zmianie nazwy hosta: przed tym krokiem wpis tunelowy
     * trzymał nazwę i psuł się po cichu, gdy ktoś ją poprawił.
     *
     * @return array{?string, ?int}
     */
    private function resolveTunnelTarget(DockerEnvironment $entry): array
    {
        if ($entry->target === '') {
            return [null, $entry->port];
        }

        $rows = $this->state->queries()->ask(
            'address-book.entry',
            new CommandInput(['entry' => $entry->target, 'chapter' => 'ssh']),
        )->rows();

        foreach ($rows as $row) {
            $host = $row['host'] ?? '';
            $user = $row['user'] ?? '';
            $port = $row['port'] ?? DockerEnvironment::DEFAULT_TUNNEL_PORT;

            if (!is_string($host) || $host === '') {
                break;
            }

            return [
                is_string($user) && $user !== '' ? $user . '@' . $host : $host,
                is_int($port) ? $port : $entry->port,
            ];
        }

        return [null, $entry->port];
    }

    /** @return list<Column> */
    private function columns(): array
    {
        return [
            Column::flexible(self::NAME_MINIMUM, label: $this->text('env.column.name')),
            Column::fixed(self::KIND_COLUMN, yieldOrder: 2, label: $this->text('env.column.kind')),
            Column::flexible(self::ADDRESS_MINIMUM, label: $this->text('env.column.address')),
            Column::fixed(self::ORIGIN_COLUMN, yieldOrder: 1, label: $this->text('env.column.origin')),
            Column::fixed(
                self::STATE_COLUMN,
                yieldOrder: 3,
                align: Align::Right,
                label: $this->text('env.column.state'),
            ),
        ];
    }

    /** @return list<TableRow> */
    private function rows(): array
    {
        $view = $this->reader->environments();
        $rows = [];

        foreach ($view->rows as $row) {
            $rows[] = new TableRow(
                [
                    $row->name,
                    $this->text('env.kind.' . $row->kind, fallback: $row->kind),
                    $row->address,
                    $this->translator->translate($row->origin->labelKey()),
                    $this->stateOf($row, $view->tunnel->stage),
                ],
                self::roleOf($row, $view->tunnel->stage),
            );
        }

        return $rows;
    }

    /** Kolumna stanu: wybór, stan tunelu przy wpisie tunelowym, przysłonięcie. */
    private function stateOf(EnvironmentRow $row, TunnelStage $tunnel): string
    {
        if ($row->shadowed) {
            return $this->text('env.state.shadowed');
        }

        if (!$row->current) {
            return '';
        }

        if ($row->kind === EnvironmentKind::SshTunnel->value && $tunnel !== TunnelStage::None) {
            return $this->text(substr($tunnel->labelKey(), strlen('module.docker.')));
        }

        return $this->text('env.state.current');
    }

    /**
     * Kolor wiersza mówi o stanie, zanim przeczyta się kolumnę — role wedle
     * lekcji z kroku 43: `Marked` dla wybranego, `Danger` dla tunelu, który
     * nie wstał, `Muted` dla wiersza przysłoniętego.
     */
    private static function roleOf(EnvironmentRow $row, TunnelStage $tunnel): Role
    {
        if ($row->shadowed) {
            return Role::Muted;
        }

        if (!$row->current) {
            return Role::Text;
        }

        if ($row->kind === EnvironmentKind::SshTunnel->value && $tunnel === TunnelStage::Failed) {
            return Role::Danger;
        }

        if ($row->kind === EnvironmentKind::SshTunnel->value && $tunnel === TunnelStage::Starting) {
            return Role::Accent;
        }

        return Role::Marked;
    }
    private function underCursor(): ?EnvironmentRow
    {
        $this->clampSelection();

        return $this->reader->environments()->at($this->selected);
    }

    private function putSelection(int $index): ScreenOutcome
    {
        $count = $this->reader->environments()->count();

        if ($count === 0) {
            $this->selected = 0;

            return ScreenOutcome::stay();
        }

        $this->selected = max(0, min($index, $count - 1));

        return ScreenOutcome::stay();
    }

    private function clampSelection(): void
    {
        $count = $this->reader->environments()->count();
        $this->selected = $count === 0 ? 0 : max(0, min($this->selected, $count - 1));
    }

    private function key(string $suffix): string
    {
        return 'module.' . DockerSettings::ID . '.' . $suffix;
    }

    /** @param array<string, string|int|float> $parameters */
    private function text(string $key, array $parameters = [], ?string $fallback = null): string
    {
        $full = 'module.' . DockerSettings::ID . '.' . $key;
        $translated = $this->translator->translate($full, $parameters);

        return $fallback !== null && $translated === $full ? $fallback : $translated;
    }
}
