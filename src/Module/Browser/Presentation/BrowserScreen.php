<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Application\UseCase\MoveSelectionUseCase;
use LightManager\Module\Browser\Application\UseCase\NavigateIntoDirectoryUseCase;
use LightManager\Module\Browser\Application\UseCase\NavigateUpUseCase;
use LightManager\Module\Browser\Application\UseCase\PreviewSelectedEntryUseCase;
use LightManager\Module\Browser\Application\UseCase\ToggleHiddenEntriesUseCase;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Presentation\Component\EntryList;
use LightManager\Module\Browser\Presentation\Component\PathLine;
use LightManager\Module\Browser\Presentation\Component\PreviewBox;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\Split;
use LightManager\Presentation\Ui\DrawsOwnFrame;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Presentation\Ui\SplitAxis;

/**
 * Przeglądarka plików — ekran modułu, który aplikacja pokazuje domyślnie.
 *
 * Do kroku 20 był ekranem **rdzenia**, wpisanym w dno stosu i w stan pętli. Krok
 * 21 nie zmienia w nim ani jednego klawisza i ani jednego napisu — zmienia to,
 * skąd bierze katalog (z `BrowserState`, nie z `LoopState`) i ile stref klatki
 * rysuje. Do kroku 20 rysował środkowy panel; dziś zamawia wszystkie trzy, bo
 * rdzeń stracił katalog, a wraz z nim podstawę do rysowania ścieżki i podglądu.
 *
 * Kontekstu sesji ten ekran **nie czyta** — on go wydaje. Publikacją zajmuje się
 * `BrowserState`, bo katalog zmienia nie tylko klawisz, ale i komenda
 * `browser.jump`, a dwa miejsca publikacji rozjechałyby się o klatkę.
 */
final class BrowserScreen implements ScreenInterface, DrawsOwnFrame
{
    /** Ile wierszy zapasu zostawić między zaznaczeniem a krawędzią listy. */
    public const SCROLL_MARGIN = 2;

    private const HIDDEN_MARKER_KEY = 'module.browser.hidden';

    public function __construct(
        private readonly BrowserPanes $panes,
        private readonly LoopState $state,
        private readonly MoveSelectionUseCase $moveSelection,
        private readonly NavigateIntoDirectoryUseCase $navigateInto,
        private readonly NavigateUpUseCase $navigateUp,
        private readonly ToggleHiddenEntriesUseCase $toggleHidden,
        private readonly PreviewSelectedEntryUseCase $preview,
        private readonly ChangeModuleSettingUseCase $changeSetting,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function id(): string
    {
        return BrowserSettings::ID;
    }

    public function labelKey(): string
    {
        return 'module.browser.zone.files';
    }

    /**
     * Górny pas: ścieżka, numer zaznaczenia i znacznik wpisów ukrytych.
     *
     * Do kroku 20 ścieżkę rysował rdzeń, a ekran dopisywał do niej końcówkę przez
     * `headerSuffix()`. Szczelina zniknęła razem z powodem swojego istnienia:
     * skoro ekran rysuje cały pas, nie ma czego łatać.
     *
     * Od kroku 24 pas należy do panelu **z ogniskiem** — jest jeden, a panele
     * bywają dwa. Przeniesienie ogniska zmienia więc ścieżkę u góry klatki i to
     * samo w sobie mówi, gdzie użytkownik teraz stoi; katalog panelu nieczynnego
     * widać w etykiecie jego obwódki.
     */
    public function header(): ScreenZone
    {
        return new ScreenZone(
            'layout.zone.path',
            new PathLine($this->panes->focused()->directory()->path(), $this->suffix()),
        );
    }

    /**
     * Pas podglądu jest zamawiany **zawsze**, także wtedy, gdy zaznaczony wpis nie
     * jest obrazem. Tak było przed zmianą: pas stał pusty, ale nie znikał, bo jego
     * wiersze odjęto liście już przy podziale okna. Znikanie pasa przy każdym
     * przejściu z obrazu na katalog przesuwałoby listę pod ręką użytkownika.
     */
    public function preview(): ScreenZone
    {
        return new ScreenZone(
            'layout.zone.preview',
            new PreviewBox($this->preview, $this->panes->focused()->directory()),
        );
    }

    private function suffix(): string
    {
        $suffix = '';
        $directory = $this->panes->focused()->directory();
        $selection = $directory->selection();

        if ($selection !== null) {
            $suffix .= sprintf('  —  %d/%d', $selection->index + 1, count($directory->entries()));
        }

        if ($this->panes->focused()->showsHiddenEntries()) {
            $suffix .= '  ' . $this->translator->translate(self::HIDDEN_MARKER_KEY);
        }

        return $suffix;
    }

    /**
     * Obwódki obu paneli — oddane rdzeniowi, żeby położył je na płaszczyźnie
     * pamiętanej między klatkami.
     *
     * Etykietą jest **ścieżka**, a nie słowo „PLIKI”: górny pas klatki należy do
     * panelu z ogniskiem, więc etykieta ramki jest jedynym miejscem, w którym
     * widać katalog panelu nieczynnego. Panel czynny poznaje się po akcencie
     * w nawiasach i w etykiecie.
     *
     * Pusta lista znaczy „oprawiaj mnie jak zawsze” — i tak właśnie odpowiadamy
     * przy wyłączonym podziale oraz poniżej progu szerokości.
     */
    public function ownFrame(Rect $zone): array
    {
        if (!$this->splitsIn($zone)) {
            return [];
        }

        $primitives = [];

        foreach (Split::halves($zone, $this->axis()) as $index => $bounds) {
            [$state, , $focused] = $this->panes->pane($index);
            $panel = new Panel(
                Label::fitEnd($state->directory()->path()->value, Panel::labelRoom($bounds)),
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
        if (!$this->splitsIn($bounds)) {
            [$state, $window] = $this->panes->pane($this->panes->focusesSecond() ? 1 : 0);

            return (new EntryList($state->directory(), $window, $this->translator))->draw($bounds);
        }

        return (new Split($this->list(0), $this->list(1), $this->axis()))->draw($bounds);
    }

    /** Zawartość jednego panelu — katalog, okno przewijania i wcięcie pod obwódkę. */
    private function list(int $index): EntryList
    {
        [$state, $window] = $this->panes->pane($index);

        return new EntryList($state->directory(), $window, $this->translator, framed: true);
    }

    /**
     * Czy w tym prostokącie naprawdę powstaną dwa panele: użytkownik włączył
     * podział **i** mieści się on w oknie. Poniżej progu wszystko wygląda tak,
     * jak przed krokiem 24.
     */
    private function splitsIn(Rect $zone): bool
    {
        return $this->splits() && $zone->rows >= 3 && Split::fits($zone, $this->axis());
    }

    public function bindings(): array
    {
        $bindings = [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move'),
            KeyBinding::of([Key::Enter, Key::ArrowRight], 'module.browser.help.open'),
            KeyBinding::of([Key::Backspace, Key::ArrowLeft], 'module.browser.help.up'),
            KeyBinding::character('.', 'module.browser.help.hidden'),
        ];

        // Klawisz ogniska pokazujemy dopiero wtedy, gdy podział jest włączony:
        // podpowiedź o przenoszeniu ogniska między panelami, których nie ma,
        // byłaby kłamstwem — a pasek stanu i spis klawiszy mają jedno źródło.
        if ($this->splits()) {
            $bindings[] = KeyBinding::of([Key::Tab], 'module.browser.help.focus');
        }

        return $bindings;
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        if ($key->key === Key::Tab) {
            return $this->moveFocus();
        }

        $directory = $this->panes->focused()->directory();

        return match (true) {
            $key->key === Key::ArrowUp => $this->moved($directory, up: true),
            $key->key === Key::ArrowDown => $this->moved($directory, up: false),
            $key->key === Key::Enter, $key->key === Key::ArrowRight => $this->open($directory),
            $key->key === Key::Backspace, $key->key === Key::ArrowLeft => $this->goUp($directory),
            $key->key === Key::Character && $key->raw === '.' => $this->toggleHidden(),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * `Tab` przenosi ognisko, ale wyłącznie wtedy, gdy jest dokąd: przy jednym
     * panelu klawisz **nie jest zużyty**, więc wraca do rdzenia i zachowuje się
     * tak, jak przed tym krokiem.
     */
    private function moveFocus(): ScreenOutcome
    {
        if ($this->splits()) {
            $this->panes->moveFocus();
        }

        return ScreenOutcome::stay();
    }

    /**
     * Czy podział jest włączony — wraz z **uzgodnieniem ogniska**, bo ustawienie
     * zmienia się także spoza tego ekranu (zakładka ustawień) i ognisko mogło
     * zostać na panelu, którego już nie ma.
     */
    private function splits(): bool
    {
        $enabled = BrowserSettings::split($this->state->settings());
        $this->panes->useSplit($enabled);

        return $enabled;
    }

    private function axis(): SplitAxis
    {
        return BrowserSettings::splitVertical($this->state->settings())
            ? SplitAxis::Vertical
            : SplitAxis::Horizontal;
    }

    /**
     * Ruch zaznaczenia zmienia agregat **w miejscu**, więc kontekst sesji trzeba
     * ogłosić osobno — inaczej moduł opisujący plik pokazywałby poprzedni wpis.
     */
    private function moved(Directory $directory, bool $up): ScreenOutcome
    {
        $up ? $this->moveSelection->up($directory) : $this->moveSelection->down($directory);
        $this->panes->focused()->selectionChanged();

        return ScreenOutcome::stay();
    }

    /**
     * Katalog otwieramy; na pliku `Enter` **nie robi nic**.
     *
     * Do kroku 20 otwierał okno z opisem pliku. Dziś opis należy do modułu
     * `FileInfo` i ma własny skrót (`Ctrl+D`), a `Enter` staje się w całej
     * aplikacji klawiszem **zatwierdzania** (P3): na katalogu wchodzi do środka,
     * w polu tekstowym zatwierdza wartość, w oknie komend uruchamia komendę.
     * Na pliku nie ma czego zatwierdzić — tak samo, jak na pustym katalogu.
     */
    private function open(Directory $directory): ScreenOutcome
    {
        $entered = $this->navigateInto->execute($directory, $this->panes->focused()->showsHiddenEntries());

        if ($entered !== null) {
            $this->panes->focused()->enter($entered);
        }

        return ScreenOutcome::stay();
    }

    private function goUp(Directory $directory): ScreenOutcome
    {
        $parent = $this->navigateUp->execute($directory, $this->panes->focused()->showsHiddenEntries());

        if ($parent !== null) {
            $this->panes->focused()->enter($parent);
        }

        return ScreenOutcome::stay();
    }

    /**
     * Widoczność wpisów ukrytych wymaga ponownego odczytu katalogu — i to
     * **przed** zapisem konfiguracji, nie po nim: nieudany odczyt rzuca wyjątek,
     * więc ustawienie zostaje wtedy takie, jakie było, i lista nie rozjeżdża się
     * z plikiem na dysku.
     *
     * Od kroku 21 zapisem zajmuje się `ChangeModuleSettingUseCase`, bo ustawienie
     * jest ustawieniem **modułu**. Obie drogi — ten klawisz i pozycja na zakładce
     * ustawień — kończą się w tym samym miejscu i w tym samym kluczu pliku.
     */
    private function toggleHidden(): ScreenOutcome
    {
        $show = !$this->panes->focused()->showsHiddenEntries();

        // Wpisy ukryte są ustawieniem **modułu**, więc dotyczą obu paneli naraz.
        // Odczyt idzie przez oba, zanim zapiszemy konfigurację: gdy któryś rzuci,
        // ustawienie zostaje takie, jakie było, i żadna z list nie rozjeżdża się
        // z dyskiem.
        foreach ($this->panes->all() as $pane) {
            $pane->enter($this->toggleHidden->execute($pane->directory(), $show));
        }

        [$settings, $message] = $this->changeSetting->shift(
            $this->state->settings(),
            BrowserSettings::ID,
            BrowserSettings::declaration(),
            1,
        );

        $this->state->applySettings($settings);

        return ScreenOutcome::stay($message);
    }

}
