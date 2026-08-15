<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Event\EventDeclaration;
use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\EffectAssignment;
use LightManager\Module\Audio\Application\PlaylistPlayer;
use LightManager\Module\Audio\Application\SoundEffects;
use LightManager\Module\Audio\Presentation\Component\EffectList;
use LightManager\Module\Audio\Presentation\Component\PlaylistPane;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\Split;
use LightManager\Presentation\Ui\Component\TextInput;
use LightManager\Presentation\Ui\Component\TreeView;
use LightManager\Presentation\Ui\ComponentInterface;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\DrawsOwnFrame;
use LightManager\Presentation\Ui\FocusHint;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Presentation\Ui\NeedsTime;
use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\ScrollWindow;
use LightManager\Presentation\Ui\SplitAxis;
use LightManager\Presentation\Ui\SplitState;

/**
 * Okno modułu dźwięku: efekty po lewej, playlista po prawej (kroki 45 i 46).
 *
 * **Komponentu rdzenia nie dokłada ani jednego** i to jest sprawdzian obu kroków:
 * playlista złożyła się z `ListView`, `Label`, `TextInput` i `ScrollWindow`,
 * a lewy panel — z `Table`, `Split` i `SplitState`, czyli z rzeczy, które stały
 * w rdzeniu odpowiednio od kroków 27 i 24. Gdyby efekty czegoś tu potrzebowały,
 * znaczyłoby to, że komponentów brakuje, a nie że trzeba dopisać kolejny.
 *
 * Ekran jest **trzecim sprawdzianem kontraktu modułu**: po module rysującym
 * główną funkcję aplikacji (21) i module, który nie rysuje nic (36), przyszedł
 * moduł pracujący wtedy, gdy go nie widać — najpierw taktem (45), teraz
 * odbiorem zdarzeń (46). Jedno i drugie dzieje się poza tym ekranem; okno jest
 * wyłącznie widokiem na stan i kompletem klawiszy do niego.
 *
 * **Podział zachowuje się inaczej niż w przeglądarce i to jest różnica warta
 * zapamiętania.** Tam poniżej progu szerokości drugi panel znika, a ognisko wraca
 * na pierwszy (`SplitState::useSplit(false)`). Tu panele są dwiema **różnymi**
 * rzeczami — nie dwoma widokami tego samego — więc zniknięcie jednego z nich
 * zabierałoby dostęp do połowy okna. W wąskim oknie widać zatem **panel
 * z ogniskiem**, a `Tab` nadal przenosi je na drugi.
 *
 * Utwory wchodzą trzema drogami (D82 nr 2), przypisania efektów — tymi samymi
 * trzema: `F5` bierze wpis zaznaczony w przeglądarce (przez `ReadsContext` —
 * moduł nie poznaje cudzego modułu, tylko ścieżkę), `F7` otwiera pole na ścieżkę,
 * a komenda (`audio.add`, `audio.hook`) działa także wtedy, gdy tego okna nie
 * widać.
 */
final class AudioScreen implements
    ScreenInterface,
    ReadsContext,
    Resettable,
    NeedsTime,
    DeclaresFocus,
    DrawsOwnFrame
{
    /** Znacznik utworu granego — ten sam trójkąt, którym drzewo znaczy gałąź zwiniętą. */
    private const PLAYING_MARK = TreeView::CLOSED . ' ';

    /** Wcięcie pozostałych pozycji, żeby nazwy stały w jednej kolumnie. */
    private const IDLE_MARK = '  ';

    /** Podział pionowy: efekty i playlista są listami, a listy czyta się w pionie. */
    private const AXIS = SplitAxis::Vertical;

    private readonly ScrollWindow $window;

    private readonly ScrollWindow $effectWindow;

    private readonly SplitState $split;

    private int $selected = 0;

    private int $effectSelected = 0;

    /** Pole na ścieżkę wpisywaną z ręki; `null`, gdy nikt niczego nie wpisuje. */
    private ?TextInput $input = null;

    private float $now = 0.0;

    private ModuleContext $context;

    public function __construct(
        private readonly PlaylistPlayer $player,
        private readonly SoundEffects $effects,
        private readonly EventRegistry $events,
        private readonly TranslatorPort $translator,
    ) {
        $this->window = new ScrollWindow();
        $this->effectWindow = new ScrollWindow();
        $this->split = new SplitState();
        $this->context = new ModuleContext();
        // Ognisko startuje na playliście, czyli po prawej: to ona jest tym, po co
        // otwiera się to okno, a efekty przypisuje się raz na jakiś czas.
        $this->split->moveFocus();
    }

    public function id(): string
    {
        return AudioSettings::ID;
    }

    public function labelKey(): string
    {
        return 'module.' . AudioSettings::ID . '.name';
    }

    /**
     * Górny pas: co gra teraz i w jakim trybie.
     *
     * Tryb stoi po prawej, bo odpowiada na pytanie zadawane rzadziej („co się
     * stanie, gdy ten utwór się skończy"), a nazwa utworu ma dostać całą resztę
     * wiersza — ścieżki bywają długie.
     *
     * Stany są **trzy, a nie dwa**, i różnicę widać dopiero w używaniu: utwór
     * zatrzymany pauzą wciąż jest tym, do którego wróci `Enter` albo spacja, więc
     * pas ma o nim mówić — ale nie słowem „gra", bo nie gra.
     */
    public function header(): ScreenZone
    {
        $playing = $this->player->nowPlaying();
        $left = match (true) {
            $playing === null => $this->text('nothing'),
            $this->player->isPlaying() => $this->text('nowPlaying', ['track' => $playing->name]),
            default => $this->text('paused', ['track' => $playing->name]),
        };

        return new ScreenZone(
            'module.' . AudioSettings::ID . '.zone.now',
            new Label($left, $this->text('mode.' . $this->player->mode()->value)),
        );
    }

    /**
     * Wejście na ekran przelicza dostępność plików — playlisty **i** efektów.
     *
     * Jest to jedyny moment, w którym wolno o nią zapytać dysk: takt nie ma prawa
     * dotknąć wejścia-wyjścia, a rysowanie robiłoby to trzydzieści razy na
     * sekundę. Obie listy, na które właśnie patrzymy, są za to warte jednego
     * sprawdzenia.
     */
    public function reset(): void
    {
        $this->player->refresh();
        $this->effects->refresh();
        $this->input = null;
        $this->clampSelection();
        $this->clampEffectSelection();
    }

    public function useTime(float $now): void
    {
        $this->now = $now;
    }

    public function useContext(ModuleContext $context): void
    {
        $this->context = $context;
    }

    /**
     * Obwódki obu paneli — oddane rdzeniowi, żeby położył je na płaszczyźnie
     * pamiętanej między klatkami (krok 24, reguła 11c).
     *
     * Pusta lista znaczy „oprawiaj mnie jak zawsze" i tak właśnie odpowiadamy
     * w oknie zbyt wąskim na podział: wtedy widać jeden panel, a jego oprawa
     * należy do rdzenia.
     */
    public function ownFrame(Rect $zone): array
    {
        if (!$this->splitsIn($zone)) {
            return [];
        }

        $primitives = [];
        $second = $this->split->focusesSecond();

        foreach (Split::halves($zone, self::AXIS) as $index => $bounds) {
            $focused = ($index === 1) === $second;
            $panel = new Panel(
                $this->text($index === 0 ? 'zone.effects' : 'zone.playlist'),
                Role::Border,
                $focused ? Role::Accent : Role::Border,
                $focused ? Role::Accent : Role::Muted,
            );

            foreach ($panel->draw($bounds) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        return $primitives;
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $body = $this->input === null ? $bounds : $bounds->rowsFrom(0, $bounds->rows - 1);
        $primitives = $this->body($body);

        if ($this->input !== null) {
            $this->input->useTime($this->now);

            foreach ($this->input->draw($bounds->line($bounds->rows - 1)) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        return $primitives;
    }

    /** @return list<Primitive> */
    private function body(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        if (!$this->splitsIn($bounds)) {
            return $this->pane($this->split->focusesSecond() ? 1 : 0, framed: false)->draw($bounds);
        }

        return (new Split(
            $this->pane(0, framed: true),
            $this->pane(1, framed: true),
            self::AXIS,
        ))->draw($bounds);
    }

    /** Treść jednego panelu: spis efektów albo playlista. */
    private function pane(int $index, bool $framed): ComponentInterface
    {
        if ($index === 0) {
            $this->clampEffectSelection();

            return new EffectList(
                $this->declarations(),
                $this->effects->map(),
                $this->effectWindow,
                $this->translator,
                $this->effectSelected,
                $framed,
            );
        }

        return new PlaylistPane($this->playlistRows(), $this->window, $this->selected, $framed);
    }

    /**
     * Czy podział ma w tym prostokącie sens.
     *
     * Próg jest ten sam, co w przeglądarce, i z tego samego powodu: dwie listy
     * w oknie na sześćdziesiąt kolumn mieszczą się bez reszty i mimo to przestają
     * być czytelne.
     */
    private function splitsIn(Rect $zone): bool
    {
        return !$zone->isEmpty() && $zone->rows >= 3 && Split::fits($zone, self::AXIS);
    }

    /** @return list<EventDeclaration> */
    private function declarations(): array
    {
        return $this->events->all();
    }

    /**
     * Wiersze playlisty: grany w akcencie ze znacznikiem, brakujący wyszarzony
     * i podpisany powodem.
     *
     * @return list<ListRow>
     */
    private function playlistRows(): array
    {
        $playlist = $this->player->playlist();

        if ($playlist->isEmpty()) {
            // Powód prawdziwy wyprzedza ogólny: playlista pusta dlatego, że jej
            // pliku nie dało się przeczytać, ma o tym powiedzieć wprost.
            return [new ListRow($this->player->problem() ?? $this->text('playlist.empty'), '', Role::Muted)];
        }

        $this->clampSelection();
        $playing = $playlist->playing();
        $rows = [];

        foreach ($playlist->entries() as $index => $entry) {
            $rows[] = new ListRow(
                ($index === $playing ? self::PLAYING_MARK : self::IDLE_MARK) . $entry->name,
                $entry->missing ? $this->text('playlist.missing') : '',
                match (true) {
                    $entry->missing => Role::Muted,
                    $index === $playing => Role::Accent,
                    default => Role::Text,
                },
            );
        }

        return $rows;
    }

    public function bindings(): array
    {
        $bindings = $this->focus()->bindings;

        if ($this->input !== null) {
            return $bindings;
        }

        $bindings[] = KeyBinding::of(
            [Key::Tab],
            'module.' . AudioSettings::ID . '.key.pane',
            'module.' . AudioSettings::ID . '.key.pane.short',
        );
        $bindings[] = KeyBinding::of([Key::Escape], 'help.key.back', 'help.key.back.short');

        return $bindings;
    }

    /**
     * Trzy miejsca ogniska: spis efektów, playlista i pole na ścieżkę.
     *
     * Pole jest osobnym miejscem, bo zużywa **każdy znak** — dopóki stoi, żaden
     * klawisz listy nie działa, a stopka obiecująca wtedy `F8` kłamałaby.
     * Panele są osobnymi miejscami z innego powodu: ten sam `F8` znaczy w jednym
     * „usuń pozycję z playlisty", a w drugim „zabierz zdarzeniu plik".
     */
    public function focus(): FocusHint
    {
        if ($this->input !== null) {
            $bindings = $this->input->bindings();
            $bindings[] = KeyBinding::of([Key::Enter], 'module.' . AudioSettings::ID . '.key.confirm');
            // Własny klucz, a nie `command.key.close` z okna komend: ten sam
            // klawisz znaczy tu co innego, a stopka obiecywała przez to
            // „zamknij okno komend" nad polem, które oknem komend nie jest
            // (wyszło dopiero z klatki pod XTermem).
            $bindings[] = KeyBinding::of([Key::Escape], 'module.' . AudioSettings::ID . '.key.cancel');

            return new FocusHint('module.' . AudioSettings::ID . '.focus.path', $bindings);
        }

        return $this->split->focusesSecond() ? $this->playlistFocus() : $this->effectsFocus();
    }

    private function effectsFocus(): FocusHint
    {
        $bindings = [KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short')];

        $bindings[] = KeyBinding::of(
            [Key::F5],
            'module.' . AudioSettings::ID . '.key.hook.take',
            'module.' . AudioSettings::ID . '.key.hook.take.short',
        );
        $bindings[] = KeyBinding::of(
            [Key::F7],
            'module.' . AudioSettings::ID . '.key.hook.type',
            'module.' . AudioSettings::ID . '.key.hook.type.short',
        );

        if ($this->assignmentUnderCursor() !== null) {
            $bindings[] = KeyBinding::character(
                ' ',
                'module.' . AudioSettings::ID . '.key.hook.mute',
                'module.' . AudioSettings::ID . '.key.hook.mute.short',
            );
            $bindings[] = KeyBinding::of(
                [Key::F8, Key::Delete],
                'module.' . AudioSettings::ID . '.key.hook.clear',
                'module.' . AudioSettings::ID . '.key.hook.clear.short',
            );
        }

        return new FocusHint('module.' . AudioSettings::ID . '.focus.effects', $bindings);
    }

    private function playlistFocus(): FocusHint
    {
        $bindings = [KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short')];

        if (!$this->player->playlist()->isEmpty()) {
            $bindings[] = KeyBinding::of(
                [Key::Enter],
                'module.' . AudioSettings::ID . '.key.play',
                'module.' . AudioSettings::ID . '.key.play.short',
            );
            $bindings[] = KeyBinding::character(
                ' ',
                'module.' . AudioSettings::ID . '.key.pause',
                'module.' . AudioSettings::ID . '.key.pause.short',
            );
        }

        $bindings[] = KeyBinding::of(
            [Key::F5],
            'module.' . AudioSettings::ID . '.key.take',
            'module.' . AudioSettings::ID . '.key.take.short',
        );
        $bindings[] = KeyBinding::of(
            [Key::F7],
            'module.' . AudioSettings::ID . '.key.type',
            'module.' . AudioSettings::ID . '.key.type.short',
        );

        if (!$this->player->playlist()->isEmpty()) {
            $bindings[] = KeyBinding::of(
                [Key::F8, Key::Delete],
                'module.' . AudioSettings::ID . '.key.remove',
                'module.' . AudioSettings::ID . '.key.remove.short',
            );
            $bindings[] = KeyBinding::shifted(
                [Key::ArrowUp, Key::ArrowDown],
                'module.' . AudioSettings::ID . '.key.move',
                'module.' . AudioSettings::ID . '.key.move.short',
            );
        }

        return new FocusHint('module.' . AudioSettings::ID . '.focus.playlist', $bindings);
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        if ($this->input !== null) {
            return $this->toInput($key);
        }

        if ($key->key === Key::Tab) {
            $this->split->moveFocus();

            return ScreenOutcome::stay();
        }

        if ($key->key === Key::Escape) {
            return ScreenOutcome::close();
        }

        return $this->split->focusesSecond() ? $this->inPlaylist($key) : $this->inEffects($key);
    }

    /** Klawisze spisu efektów. */
    private function inEffects(KeyPress $key): ScreenOutcome
    {
        return match (true) {
            $key->key === Key::ArrowUp => $this->selectEffect(-1),
            $key->key === Key::ArrowDown => $this->selectEffect(1),
            $key->key === Key::Home => $this->selectEffect(-PHP_INT_MAX),
            $key->key === Key::End => $this->selectEffect(PHP_INT_MAX),
            $key->key === Key::F5 => $this->hookFromContext(),
            $key->key === Key::F7 => $this->askForPath(),
            $key->key === Key::F8, $key->key === Key::Delete => $this->clearHook(),
            $key->key === Key::Character && $key->raw === ' ' && !$key->ctrl && !$key->alt => $this->muteHook(),
            default => ScreenOutcome::stay(),
        };
    }

    /** Klawisze playlisty — te same, co przed krokiem 46. */
    private function inPlaylist(KeyPress $key): ScreenOutcome
    {
        // `Shift` rozstrzyga się **przed** gałęziami klawiszy, wzorem
        // `BrowserScreen::shifted()`: goła strzałka nie ma prawa złapać
        // przestawienia, a przestawienie — ruchu kursora.
        if ($key->shift && ($key->key === Key::ArrowUp || $key->key === Key::ArrowDown)) {
            return $this->moved($key->key === Key::ArrowUp ? -1 : 1);
        }

        return match (true) {
            $key->key === Key::ArrowUp => $this->select(-1),
            $key->key === Key::ArrowDown => $this->select(1),
            $key->key === Key::Home => $this->select(-PHP_INT_MAX),
            $key->key === Key::End => $this->select(PHP_INT_MAX),
            $key->key === Key::Enter => $this->play(),
            $key->key === Key::F5 => $this->takeFromContext(),
            $key->key === Key::F7 => $this->askForPath(),
            $key->key === Key::F8, $key->key === Key::Delete => $this->remove(),
            $key->key === Key::Character && $key->raw === ' ' && !$key->ctrl && !$key->alt => $this->pause(),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * Klawisze pola na ścieżkę. Pole zużywa **każdy znak**, więc ekran dostaje
     * z powrotem wyłącznie `Enter` i `Esc` — dokładnie tak, jak w trybie edycji
     * pozycji tekstowej na ekranie ustawień (krok 20).
     *
     * Wpisana ścieżka trafia tam, gdzie stało ognisko, gdy pole się otwierało —
     * a ognisko nie ma jak się w międzyczasie przenieść, bo `Tab` też idzie do
     * pola.
     */
    private function toInput(KeyPress $key): ScreenOutcome
    {
        $input = $this->input;

        if ($input === null) {
            return ScreenOutcome::stay();
        }

        if ($key->key === Key::Escape) {
            $this->input = null;

            return ScreenOutcome::stay();
        }

        if ($key->key === Key::Enter) {
            $path = $input->value();
            $this->input = null;

            return $this->split->focusesSecond() ? $this->added($path) : $this->hooked($path);
        }

        $input->handle($key);

        return ScreenOutcome::stay();
    }

    private function select(int $delta): ScreenOutcome
    {
        $total = $this->player->playlist()->count();

        if ($total === 0) {
            return ScreenOutcome::stay();
        }

        $this->selected = max(0, min($total - 1, $this->selected + $delta));

        return ScreenOutcome::stay();
    }

    private function selectEffect(int $delta): ScreenOutcome
    {
        $total = count($this->declarations());

        if ($total === 0) {
            return ScreenOutcome::stay();
        }

        $this->effectSelected = max(0, min($total - 1, $this->effectSelected + $delta));

        return ScreenOutcome::stay();
    }

    /** Przestawienie pozycji — kursor wędruje **razem z nią**, bo wskazuje utwór. */
    private function moved(int $direction): ScreenOutcome
    {
        $this->selected = $this->player->move($this->selected, $direction);

        return ScreenOutcome::stay();
    }

    private function play(): ScreenOutcome
    {
        $problem = $this->player->play($this->selected);

        return $problem === null
            ? ScreenOutcome::stay()
            : ScreenOutcome::stay(Message::error($problem));
    }

    /**
     * Pauza i wznowienie na jednym klawiszu — bo silnik **pauzuje**, a nie
     * przewija (krok 36), więc rozdzielenie ich obiecywałoby różnicę, której pod
     * spodem nie ma.
     */
    private function pause(): ScreenOutcome
    {
        if ($this->player->isPlaying()) {
            $this->player->pause();

            return ScreenOutcome::stay();
        }

        $problem = $this->player->resume();

        return $problem === null
            ? ScreenOutcome::stay()
            : ScreenOutcome::stay(Message::error($problem));
    }

    private function remove(): ScreenOutcome
    {
        if (!$this->player->remove($this->selected)) {
            return ScreenOutcome::stay();
        }

        $this->clampSelection();

        return ScreenOutcome::stay();
    }

    /**
     * Wpis zaznaczony w przeglądarce — **przez kontekst sesji**, a nie przez
     * poznanie cudzego modułu.
     *
     * Kontekst pusty jest zwykłym stanem (katalog pusty albo nieczytelny), więc
     * kończy się zdaniem, a nie milczeniem: klawisz, który nic nie robi i nic nie
     * mówi, wygląda jak zepsuty.
     */
    private function takeFromContext(): ScreenOutcome
    {
        $path = $this->context->selectionPath();

        if ($path === null) {
            return ScreenOutcome::stay(Message::warning($this->text('playlist.noSelection')));
        }

        return $this->added($path);
    }

    private function hookFromContext(): ScreenOutcome
    {
        $path = $this->context->selectionPath();

        if ($path === null) {
            return ScreenOutcome::stay(Message::warning($this->text('playlist.noSelection')));
        }

        return $this->hooked($path);
    }

    private function askForPath(): ScreenOutcome
    {
        $this->input = new TextInput($this->text('playlist.path') . ': ');

        return ScreenOutcome::stay();
    }

    /** Nowa pozycja staje na końcu listy i **kursor idzie za nią** — widać, co się dodało. */
    private function added(string $path): ScreenOutcome
    {
        $problem = $this->player->add($path);

        if ($problem !== null) {
            return ScreenOutcome::stay(Message::error($problem));
        }

        $this->selected = max(0, $this->player->playlist()->count() - 1);

        return ScreenOutcome::stay(Message::info($this->text('playlist.added', ['track' => basename($path)])));
    }

    /** Przypisanie pliku zdarzeniu pod kursorem. */
    private function hooked(string $path): ScreenOutcome
    {
        $declaration = $this->declarationUnderCursor();

        if ($declaration === null) {
            return ScreenOutcome::stay();
        }

        if (!$this->effects->assign($declaration->name, $path)) {
            return ScreenOutcome::stay(Message::error($this->text('track.empty')));
        }

        return ScreenOutcome::stay(Message::info($this->text('effect.assigned', [
            'event' => $this->translator->translate($declaration->labelKey),
            'file' => basename($path),
        ])));
    }

    private function clearHook(): ScreenOutcome
    {
        $declaration = $this->declarationUnderCursor();

        if ($declaration === null || !$this->effects->clear($declaration->name)) {
            return ScreenOutcome::stay();
        }

        return ScreenOutcome::stay(Message::info($this->text('effect.cleared', [
            'event' => $this->translator->translate($declaration->labelKey),
        ])));
    }

    /**
     * Wyciszenie **zostawia plik**, a `F8` go zabiera — i to jest cała różnica
     * między tymi dwoma klawiszami.
     */
    private function muteHook(): ScreenOutcome
    {
        $declaration = $this->declarationUnderCursor();

        if ($declaration === null) {
            return ScreenOutcome::stay();
        }

        $this->effects->toggle($declaration->name);

        return ScreenOutcome::stay();
    }

    private function declarationUnderCursor(): ?EventDeclaration
    {
        $this->clampEffectSelection();

        return $this->declarations()[$this->effectSelected] ?? null;
    }

    private function assignmentUnderCursor(): ?EffectAssignment
    {
        $declaration = $this->declarationUnderCursor();

        return $declaration === null ? null : $this->effects->map()->at($declaration->name);
    }

    private function clampSelection(): void
    {
        $total = $this->player->playlist()->count();
        $this->selected = $total === 0 ? 0 : max(0, min($total - 1, $this->selected));
    }

    private function clampEffectSelection(): void
    {
        $total = count($this->declarations());
        $this->effectSelected = $total === 0 ? 0 : max(0, min($total - 1, $this->effectSelected));
    }

    /** @param array<string, string> $parameters */
    private function text(string $key, array $parameters = []): string
    {
        return $this->translator->translate('module.' . AudioSettings::ID . '.' . $key, $parameters);
    }
}
