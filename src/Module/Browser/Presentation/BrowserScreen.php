<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserEvent;
use LightManager\Module\Browser\Application\BrowserEvents;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Application\UseCase\MoveSelectionUseCase;
use LightManager\Module\Browser\Application\UseCase\NavigateIntoDirectoryUseCase;
use LightManager\Module\Browser\Application\UseCase\NavigateUpUseCase;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\ValueObject\NameFilter;
use LightManager\Module\Browser\Presentation\Component\EntryList;
use LightManager\Module\Browser\Presentation\Component\EntrySize;
use LightManager\Module\Browser\Presentation\Component\EntryTree;
use LightManager\Module\Browser\Presentation\Component\PathLine;
use LightManager\Module\Browser\Presentation\Overlay\FilterOverlay;
use LightManager\Presentation\Cli\Query\CoreReader;
use LightManager\Presentation\Cli\SplitSetting;
use LightManager\Presentation\Ui\AcceptsPointer;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\Split;
use LightManager\Presentation\Ui\ComponentInterface;
use LightManager\Presentation\Ui\DeclaresFocus;
use LightManager\Presentation\Ui\DrawsOwnFrame;
use LightManager\Presentation\Ui\FocusHint;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\PointerRow;
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
 * rysuje. Do kroku 20 rysował środkowy panel; od kroku 21 zamawia także pas
 * ścieżki, bo rdzeń stracił katalog, a wraz z nim podstawę do jej rysowania.
 * Pas podglądu zamawiał do D76, a od kroku 47 nie ma go w kontrakcie wcale.
 *
 * Kontekstu sesji ten ekran **nie czyta** — on go wydaje. Publikacją zajmuje się
 * `BrowserState`, bo katalog zmienia nie tylko klawisz, ale i komenda
 * `browser.jump`, a dwa miejsca publikacji rozjechałyby się o klatkę.
 */
final class BrowserScreen implements ScreenInterface, DrawsOwnFrame, DeclaresFocus, AcceptsPointer
{
    /** Ile wierszy zapasu zostawić między zaznaczeniem a krawędzią listy. */
    public const SCROLL_MARGIN = 2;

    private const HIDDEN_MARKER_KEY = 'module.browser.hidden';

    /**
     * Znacznik zawężenia w pasie ścieżki.
     *
     * Filtr **przeżywa zamknięcie okna**, więc musi być widoczny również wtedy,
     * gdy pola już nie ma na ekranie. Bez tego lista zawężona byłaby nieodróżnialna
     * od katalogu, w którym po prostu nie ma tych plików — a to jest dokładnie ta
     * pomyłka, którą znacznik wpisów ukrytych rozwiązał w kroku 21.
     */
    private const FILTER_MARKER_KEY = 'module.browser.filter.marker';

    /**
     * Zdanie o tym, dlaczego gałąź się nie rozwija — jedyny komunikat, który
     * drzewo w ogóle wypisuje.
     */
    private const DEPTH_LIMIT_KEY = 'module.browser.tree.depth';

    /** Podsumowanie zbioru w pasie ścieżki — wariant z katalogami i bez (krok 43). */
    private const MARKED_SUMMARY_KEY = 'module.browser.marked.summary';

    private const MARKED_SUMMARY_DIRS_KEY = 'module.browser.marked.summary.dirs';

    /**
     * Znaki zaznaczania: spacja przełącza wpis pod kursorem, gwiazdka odwraca
     * zaznaczenie na liście widocznej (krok 43).
     *
     * Spacja przychodzi jako `Key::Character` z `raw === ' '` — osobnego
     * `Key::Space` w słowniku wejścia nie ma i ten krok go nie wprowadza, bo
     * spacja jest znakiem, a nie klawiszem sterującym: w polu tekstowym ma
     * zostać spacją.
     */
    private const MARK_KEY = ' ';

    private const INVERT_KEY = '*';

    /**
     * Litera przełączająca widok panelu na drzewo i z powrotem — **z `Ctrl`**,
     * rozstrzygnięcie użytkownika ze startu kroku 31.
     *
     * Trzeba przy tym wiedzieć, gdzie ten klawisz mieszka: `Ctrl`+litera jest od
     * kroku 19 przestrzenią **skrótów modułów**, sprawdzaną w `InputHandler` przed
     * ekranem. Litera bez zarejestrowanego modułu przechodzi niżej i dlatego to
     * działa — ale moduł ze skrótem `t` przejąłby ją cicho. Pilnuje tego
     * `BrowserShortcutsTest`, żeby kolizja wyszła na testach, a nie na klawiaturze.
     */
    private const TREE_KEY = 't';

    /**
     * Litera cofania — z `Alt`, bo `Ctrl`+litera należy w całości do skrótów
     * modułów (krok 20), a `F9` od kroku 32 otwiera menu. `Alt`+litera jest
     * w przeglądarce wolne w całości; `Alt`+`z` z modułu opisu pliku nie
     * koliduje, bo skróty ekranów nie wychodzą poza własny ekran (krok 44,
     * D81 nr 9).
     */
    private const UNDO_KEY = 'u';

    /** Domyślny podział paneli w procentach — ten sam, którym `BrowserModule` opisuje pozycję. */
    private const SPLIT_PERCENT = 50;

    /**
     * Prostokąt z ostatniego rysowania — pamięć wymagana przez `AcceptsPointer`
     * (krok 55, reguła 11z). `null` do pierwszej klatki: kliknięcie przed nią
     * nie ma prawa się zdarzyć, ale nie ma też czego trafić.
     */
    private ?Rect $lastBounds = null;

    public function __construct(
        private readonly BrowserPanes $panes,
        private readonly BrowserQueries $queries,
        /** Odczyt ustawień rdzenia — przez rejestr kwerend (krok 53, D92 nr 3). */
        private readonly CoreReader $core,
        private readonly MoveSelectionUseCase $moveSelection,
        private readonly NavigateIntoDirectoryUseCase $navigateInto,
        private readonly NavigateUpUseCase $navigateUp,
        private readonly HiddenEntries $hidden,
        private readonly TranslatorPort $translator,
        private readonly EntryOperations $entries,
        private readonly EntryTransfer $transfers,
        private readonly EntryTrash $trash,
        private readonly EntryUndo $undo,
        private readonly BrowserEvents $events,
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
        // Ścieżka rysowana pochodzi z kwerendy, a nie ze stanu panelu (krok 53):
        // odczyt idzie rejestrem także wewnątrz modułu, który dane wystawia.
        return new ScreenZone(
            'layout.zone.path',
            new PathLine($this->directory()->path(), $this->suffix()),
        );
    }

    /**
     * Katalog panelu czynnego — **przez rejestr kwerend** (krok 53).
     *
     * Zapasowe sięgnięcie do stanu paneli nie jest ostrożnością na zapas: moduł
     * niezarejestrowany w rejestrze kwerend nie widzi własnych danych, a ekran
     * bez katalogu nie miałby czego narysować. W aplikacji ta gałąź nie pada
     * nigdy — rejestr wypełnia `Bootstrap`, zanim powstanie pierwszy ekran.
     */
    private function directory(): Directory
    {
        return $this->queries->directory();
    }

    private function suffix(): string
    {
        $suffix = '';
        $directory = $this->directory();
        $selection = $directory->selection();

        if ($this->queries->showsTree()) {
            // W drzewie liczy się **węzły**, nie wpisy katalogu: numer wpisu
            // w korzeniu nie powiedziałby nic o tym, gdzie stoi kursor po
            // rozwinięciu trzech gałęzi.
            $tree = $this->queries->treeOf();
            $index = $tree->cursorIndex();

            if ($index !== null) {
                $suffix .= sprintf('  —  %d/%d', $index + 1, $tree->count());
            }
        } elseif ($selection !== null) {
            $suffix .= sprintf('  —  %d/%d', $selection->index + 1, count($directory->entries()));
        }

        if ($this->queries->showsHidden()) {
            $suffix .= '  ' . $this->translator->translate(self::HIDDEN_MARKER_KEY);
        }

        $filter = $this->queries->filter();

        if ($filter !== '') {
            $suffix .= '  ' . $this->translator->translate(
                self::FILTER_MARKER_KEY,
                ['fragment' => $filter],
            );
        }

        return $suffix . $this->markedSummary();
    }

    /**
     * Podsumowanie zbioru w pasie ścieżki: „12 z 340 · 4,1 GB” (krok 43).
     *
     * Liczba całkowita jest liczbą wpisów **katalogu pełnego**, a nie widocznej
     * listy, i to jest wprost skutek rozstrzygnięcia 4: zbiór przeżywa zawężenie
     * filtrem, więc „12 z 340” przy widocznych trzydziestu wpisach mówi prawdę,
     * a „12 z 30” mówiłoby, że dziewięć zaznaczonych wpisów nie istnieje.
     *
     * **Suma rozmiarów pomija katalogi i napis musi to powiedzieć** — inaczej
     * kłamie (rozstrzygnięcie 7). Zajętość katalogu wraz z zawartością umie
     * policzyć wyłącznie `du` z kroku 26, czyli praca tłowa, a pas ścieżki liczy
     * się co klatkę.
     */
    private function markedSummary(): string
    {
        $marked = $this->queries->marked();

        if ($marked->isEmpty()) {
            return '';
        }

        $directories = $marked->directories();
        $parameters = [
            'count' => $this->translator->number((float) $marked->count()),
            'total' => $this->translator->number((float) $this->queries->fullCount()),
            'size' => EntrySize::of($this->translator, $marked->bytes()),
        ];

        if ($directories > 0) {
            $parameters['dirs'] = $this->translator->number((float) $directories);
        }

        return '  ' . $this->translator->translate(
            $directories > 0 ? self::MARKED_SUMMARY_DIRS_KEY : self::MARKED_SUMMARY_KEY,
            $parameters,
        );
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
            $focused = $this->queries->focusesSecond() === ($index === 1);
            $path = $this->queries->directory($index)->path()->value;
            $panel = new Panel(
                Label::fitEnd($path, Panel::labelRoom($bounds)),
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
        // Prostokąt z ostatniego rysowania — jedyna pamięć, jaką reguła 11z
        // przewiduje dla trafienia wskaźnika. Rdzeń mapy nie prowadzi; ekran
        // pamięta **swój** prostokąt, bo to on go dostał (krok 55).
        $this->lastBounds = $bounds;

        if (!$this->splitsIn($bounds)) {
            return $this->pane($this->queries->focusesSecond() ? 1 : 0, framed: false)->draw($bounds);
        }

        return (new Split(
            $this->pane(0, framed: true),
            $this->pane(1, framed: true),
            $this->axis(),
            $this->panes->split()->fraction(),
        ))->draw($bounds);
    }

    /**
     * Kliknięcie, przeciągnięcie i kółko w liście plików — miara kroku 55
     * („kliknięcie w wiersz listy stawia na nim kursor we wszystkich trzech
     * torach”).
     *
     * Kolejność pytań jest kolejnością pierwszeństwa i nie wolno jej odwrócić:
     * najpierw **granica podziału**, bo leży na styku obu paneli i kliknięcie
     * w nią nie jest kliknięciem w żaden z nich; potem panel, bo trzeba wiedzieć,
     * czyj jest kursor; na końcu wiersz.
     */
    public function pointer(PointerEvent $event): ScreenOutcome
    {
        $bounds = $this->lastBounds;

        if ($bounds === null) {
            return ScreenOutcome::stay();
        }

        $split = $this->splitsIn($bounds);

        if ($split && $this->panes->split()->pointer($event, $bounds, $this->axis())) {
            return ScreenOutcome::stay();
        }

        [$index, $pane] = $this->paneAt($event, $bounds, $split);

        if ($pane === null) {
            return ScreenOutcome::stay();
        }

        // Ognisko idzie za wskaźnikiem także przy kółku: przewijanie panelu,
        // którego klawisze nie dotyczą, byłoby przewijaniem na oślep.
        $this->panes->focusPane($index);
        $this->panes->publishFocused();

        if ($event->isScroll()) {
            return $this->scrolled($index, $event->scrollRows());
        }

        if ($event->action !== PointerAction::Press || $event->button === PointerButton::Middle) {
            return ScreenOutcome::stay();
        }

        return $this->queries->showsTree($index)
            ? $this->treeCursorAt($index, $event, $pane)
            : $this->listCursorAt($index, $event, $pane);
    }

    /**
     * Który panel wskazano wraz z prostokątem jego **treści** (bez obwódki).
     *
     * Przy wyłączonym podziale panel jest jeden i jest nim ten z ogniskiem —
     * czyli kliknięcie ogniska nie przenosi, bo nie ma dokąd.
     *
     * @return array{int, ?Rect}
     */
    private function paneAt(PointerEvent $event, Rect $bounds, bool $split): array
    {
        if (!$split) {
            $index = $this->queries->focusesSecond() ? 1 : 0;

            return [$index, $event->hits($bounds) ? $bounds : null];
        }

        foreach (Split::halves($bounds, $this->axis(), $this->panes->split()->fraction()) as $index => $half) {
            if ($event->hits($half)) {
                return [$index, Panel::inner($half)];
            }
        }

        return [0, null];
    }

    /** Kursor listy postawiony na wskazanym wierszu. */
    private function listCursorAt(int $index, PointerEvent $event, Rect $content): ScreenOutcome
    {
        $directory = $this->queries->directory($index);
        $row = PointerRow::of(
            $event,
            $content,
            $this->panes->pane($index)[1]->offset(),
            $this->columnHeader(),
            count($directory->entries()),
        );

        if ($row === null) {
            return ScreenOutcome::stay();
        }

        $this->moveSelection->to($directory, $row);
        $this->panes->pane($index)[0]->selectionChanged();
        $this->panes->publishFocused();
        $this->events->fire(BrowserEvent::CursorMoved);

        return ScreenOutcome::stay();
    }

    /** Kursor drzewa: numer wiersza wskazuje węzeł w spłaszczonej liście (krok 31). */
    private function treeCursorAt(int $index, PointerEvent $event, Rect $content): ScreenOutcome
    {
        $tree = $this->queries->treeOf($index);
        $nodes = $tree->nodes();
        $row = PointerRow::of($event, $content, $tree->window()->offset(), false, count($nodes));

        if ($row === null) {
            return ScreenOutcome::stay();
        }

        $tree->state()->moveTo($nodes[$row]->key);
        $this->panes->publishFocused();
        $this->events->fire(BrowserEvent::CursorMoved);

        return ScreenOutcome::stay();
    }

    /**
     * Przewinięcie kółkiem — **bez ruszania kursora**.
     *
     * Okno przewijania odczepia się przy tym od kursora i wraca do niego dopiero
     * przy pierwszym ruchu klawiszem (krok 55). Bez tego odczepienia
     * `keepVisible()` ściągałby okno z powrotem w tej samej klatce, w której
     * kółko je przesunęło — bo panel listowy woła je przy każdym rysowaniu.
     */
    private function scrolled(int $index, int $rows): ScreenOutcome
    {
        $window = $this->queries->showsTree($index)
            ? $this->queries->treeOf($index)->window()
            : $this->panes->pane($index)[1];

        $window->scrollBy($rows);

        return ScreenOutcome::stay();
    }

    /**
     * Zawartość jednego panelu — lista albo drzewo, wraz z wcięciem pod obwódkę.
     *
     * Wybór widoku należy do panelu (krok 31) i jest **jedynym** miejscem, w którym
     * ekran o drzewie w ogóle wie przy rysowaniu: dalej obie gałęzie są zwykłym
     * komponentem, a podział, oprawa i strefy zostają nietknięte.
     */
    private function pane(int $index, bool $framed): ComponentInterface
    {
        // Z paneli bierzemy **wyłącznie okno przewijania**: to stan między
        // klatkami (reguła 11a), a nie dana — kwerendy go nie niosą i nieść nie
        // mają. Wszystko, co widać w liście, przychodzi z rejestru.
        $window = $this->panes->pane($index)[1];

        if ($this->queries->showsTree($index)) {
            return new EntryTree(
                $this->queries->treeOf($index),
                $this->translator,
                $framed,
                new NameFilter($this->queries->filter($index)),
            );
        }

        return new EntryList(
            $this->queries->directory($index),
            $window,
            $this->translator,
            framed: $framed,
            details: $this->details(),
            header: $this->columnHeader(),
            filter: new NameFilter($this->queries->filter($index)),
            marked: $this->queries->marked($index),
        );
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

    /**
     * Ognisko przeglądarki to **panel czynny** (krok 40).
     *
     * Nazwa miejsca zmienia się wraz z tym, co je odróżnia: przy podziale liczy
     * się, **który** panel ma kursor, więc etykietą jest jego położenie; przy
     * jednym panelu położenie nie mówi nic, więc etykietą jest **widok** — bo to
     * on rozstrzyga, co znaczą strzałki poziome.
     */
    public function focus(): FocusHint
    {
        return new FocusHint($this->focusLabelKey(), $this->paneBindings());
    }

    private function focusLabelKey(): string
    {
        if (!$this->splits()) {
            return $this->queries->showsTree()
                ? 'module.browser.focus.tree'
                : 'module.browser.focus.list';
        }

        $second = $this->queries->focusesSecond();

        return $this->axis() === SplitAxis::Vertical
            ? ($second ? 'module.browser.focus.right' : 'module.browser.focus.left')
            : ($second ? 'module.browser.focus.bottom' : 'module.browser.focus.top');
    }

    /**
     * Klawisze **panelu z ogniskiem**: ruch kursora i wędrówka po katalogach.
     *
     * Strzałki poziome znaczą w drzewie **co innego** niż w liście i to jest cały
     * powód, dla którego spis rozdziela się na dwa: w liście `→` wchodzi do
     * katalogu, w drzewie rozwija gałąź. Jedna wspólna lista musiałaby opisać oba
     * znaczenia naraz, czyli skłamać w połowie przypadków — a precedens z kroku 30
     * mówi, że spis pokazuje wyłącznie to, co działa tu i teraz.
     *
     * @return list<KeyBinding>
     */
    private function paneBindings(): array
    {
        if ($this->queries->showsTree()) {
            return [
                KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short'),
                KeyBinding::of(
                    [Key::ArrowRight],
                    'module.browser.help.tree.expand',
                    'module.browser.help.tree.expand.short',
                ),
                KeyBinding::of(
                    [Key::ArrowLeft],
                    'module.browser.help.tree.collapse',
                    'module.browser.help.tree.collapse.short',
                ),
                KeyBinding::of([Key::Enter], 'module.browser.help.open', 'module.browser.help.open.short'),
                KeyBinding::of([Key::Backspace], 'module.browser.help.up', 'module.browser.help.up.short'),
            ];
        }

        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move', 'help.key.move.short'),
            KeyBinding::of(
                [Key::Enter, Key::ArrowRight],
                'module.browser.help.open',
                'module.browser.help.open.short',
            ),
            KeyBinding::of(
                [Key::Backspace, Key::ArrowLeft],
                'module.browser.help.up',
                'module.browser.help.up.short',
            ),
            // Zaznaczanie należy do **listy**, nie do ekranu, i to jest cała
            // treść rozstrzygnięcia 9: w drzewie spacja nie robi nic, bo zbiór
            // trzyma nazwy z jednego katalogu, a węzły leżą na różnych
            // poziomach. Spis mówi więc o niej wyłącznie tam, gdzie działa.
            KeyBinding::character(
                self::MARK_KEY,
                'module.browser.help.mark',
                'module.browser.help.mark.short',
            ),
            // Zaznaczanie zakresem — spacja bez podnoszenia palca (krok 44,
            // D81 nr 12). Tą samą regułą, co spacja: wyłącznie w liście.
            KeyBinding::shifted(
                [Key::ArrowUp, Key::ArrowDown],
                'module.browser.help.markRange',
                'module.browser.help.markRange.short',
            ),
            KeyBinding::character(
                self::INVERT_KEY,
                'module.browser.help.invert',
                'module.browser.help.invert.short',
            ),
        ];
    }

    /**
     * Pełny spis: klawisze panelu z ogniskiem **plus** te, które należą do ekranu.
     *
     * Kolejność jest kolejnością czytania stopki — od tego, co dotyczy kursora, po
     * to, co dotyczy całej przeglądarki — a okno pomocy dostaje jedno i drugie,
     * bo zostaje **pełnym** spisem. Powtórzenia odsiewa `StatusHints`, i to jest
     * jedyne miejsce, w którym ten odsiew ma cokolwiek do roboty.
     */
    public function bindings(): array
    {
        $bindings = [
            ...$this->paneBindings(),
            KeyBinding::character('.', 'module.browser.help.hidden', 'module.browser.help.hidden.short'),
            KeyBinding::character('/', 'module.browser.help.filter', 'module.browser.help.filter.short'),
            KeyBinding::ctrl(self::TREE_KEY, 'module.browser.help.tree', 'module.browser.help.tree.short'),
            // Pięć czynności zmieniających dysk: trzy z kroku 41 i dwie z kroku 42.
            // Klawisze z układu klasycznych menadżerów, i to on rozstrzygnął spór
            // o `F6`: para `F5` kopiowanie / `F6` przeniesienie jest tam jedną
            // rzeczą, więc zmiana nazwy zeszła na wolne `F4` (D79, nr 7) — mimo że
            // `F6` znaczył ją przez cały krok 41.
            KeyBinding::of([Key::F4], 'module.browser.help.rename', 'module.browser.help.rename.short'),
            KeyBinding::of([Key::F5], 'module.browser.help.copy', 'module.browser.help.copy.short'),
            KeyBinding::of([Key::F6], 'module.browser.help.move', 'module.browser.help.move.short'),
            KeyBinding::of([Key::F7], 'module.browser.help.mkdir', 'module.browser.help.mkdir.short'),
            // Dwie drogi usunięcia (krok 44): opisy idą **za ustawieniem**, bo
            // spis pokazuje to, co klawisz naprawdę zrobi — goły klawisz wedle
            // pozycji „usuwaj do kosza”, `Shift` zawsze to drugie (D81, nr 2).
            KeyBinding::of(
                [Key::F8, Key::Delete],
                $this->deletesToTrash() ? 'module.browser.help.trash' : 'module.browser.help.delete',
                $this->deletesToTrash() ? 'module.browser.help.trash.short' : 'module.browser.help.delete.short',
            ),
            KeyBinding::shifted(
                [Key::F8, Key::Delete],
                $this->deletesToTrash() ? 'module.browser.help.delete' : 'module.browser.help.trash',
                $this->deletesToTrash() ? 'module.browser.help.delete.short' : 'module.browser.help.trash.short',
            ),
            // Cofanie (krok 44): klawisz bierze najnowszą operację odwracalną,
            // widok pokazuje cały stos.
            KeyBinding::alt(self::UNDO_KEY, 'module.browser.help.undo', 'module.browser.help.undo.short'),
            KeyBinding::of([Key::F3], 'module.browser.help.undoView', 'module.browser.help.undoView.short'),
        ];

        // Klawisz ogniska pokazujemy dopiero wtedy, gdy podział jest włączony:
        // podpowiedź o przenoszeniu ogniska między panelami, których nie ma,
        // byłaby kłamstwem — a pasek stanu i spis klawiszy mają jedno źródło.
        if ($this->splits()) {
            $bindings[] = KeyBinding::of([Key::Tab], 'module.browser.help.focus', 'module.browser.help.focus.short');
        }

        // Tą samą regułą: zdjęcie filtra pokazujemy dopiero wtedy, gdy jest co
        // zdejmować. `Esc` na liście bez filtra nie robi nic i nie ma prawa
        // twierdzić, że robi.
        //
        // Od kroku 43 `Esc` ma **dwie warstwy do zdjęcia** i opis mówi o tej,
        // która ustąpi teraz — najpierw filtr, potem zaznaczenie (rozstrzygnięcie
        // 3). Jeden opis dla obu byłby kłamstwem w połowie przypadków, a dwie
        // pozycje naraz obiecywałyby dwa różne skutki jednego naciśnięcia.
        if ($this->queries->filter() !== '') {
            $bindings[] = KeyBinding::of(
                [Key::Escape],
                'module.browser.help.filter.clear',
                'module.browser.help.filter.clear.short',
            );
        } elseif (!$this->queries->marked()->isEmpty()) {
            $bindings[] = KeyBinding::of(
                [Key::Escape],
                'module.browser.help.marked.clear',
                'module.browser.help.marked.clear.short',
            );
        }

        return $bindings;
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        // `Shift` rozstrzyga się pierwszy (krok 44): goły `F8` nie ma prawa
        // złapać `Shift`+`F8`, bo od tego kroku znaczą dwie różne rzeczy —
        // ta sama reguła, którą litera porównuje `Ctrl` i `Alt` (11j),
        // rozciągnięta na klawisze nazwane.
        if ($key->shift) {
            return $this->shifted($key);
        }

        // Litera z `Ctrl` nie jest treścią (reguła 11j) i nie jest też klawiszem
        // listy: albo przełącza widok panelu, albo nie znaczy w tym ekranie nic.
        if ($key->key === Key::Character && $key->ctrl) {
            return $key->raw === self::TREE_KEY ? $this->toggleTree() : ScreenOutcome::stay();
        }

        // Litera z `Alt` — cofnięcie ostatniej operacji odwracalnej (krok 44).
        if ($key->key === Key::Character && $key->alt) {
            return $key->raw === self::UNDO_KEY ? $this->undo->undoLatest() : ScreenOutcome::stay();
        }

        if ($key->key === Key::Tab) {
            return $this->moveFocus();
        }

        // Czynności zmieniające dysk rozstrzygają się **przed** podziałem na widoki
        // (krok 41): zmiana nazwy i usunięcie dotyczą wpisu pod kursorem, a kursor
        // ma i lista, i drzewo. Klawisz znaczący w obu widokach to samo nie ma po
        // co trafiać do dwóch gałęzi.
        if ($key->key === Key::F3) {
            return $this->undo->viewPrompt();
        }

        if ($key->key === Key::F4) {
            return $this->entries->renamePrompt();
        }

        if ($key->key === Key::F5) {
            return $this->transfers->copyPrompt();
        }

        if ($key->key === Key::F6) {
            return $this->transfers->movePrompt();
        }

        if ($key->key === Key::F7) {
            return $this->entries->directoryPrompt();
        }

        if ($key->key === Key::F8 || $key->key === Key::Delete) {
            return $this->trash->deletePrompt();
        }

        if ($this->queries->showsTree()) {
            return $this->inTree($key);
        }

        $directory = $this->directory();

        return match (true) {
            $key->key === Key::ArrowUp => $this->moved($directory, up: true),
            $key->key === Key::ArrowDown => $this->moved($directory, up: false),
            $key->key === Key::Enter, $key->key === Key::ArrowRight => $this->open($directory),
            $key->key === Key::Backspace, $key->key === Key::ArrowLeft => $this->goUp($directory),
            $key->key === Key::Escape => $this->stepBack(),
            // Litera z modyfikatorem nie jest treścią (reguła 11j): goła kropka
            // przełącza wpisy ukryte, `Ctrl`+`.` i `Alt`+`.` — nie. Oba
            // modyfikatory odpadły już wyżej — `Ctrl` z klawiszem widoku,
            // `Alt` z cofaniem (krok 44) — więc dociera tu sama goła litera.
            $key->key === Key::Character => $this->character($key->raw, marks: true),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * Znaki wspólne obu widokom, a przy `$marks` — także te należące do listy.
     *
     * Podział wprowadził krok 43 wraz z rozstrzygnięciem 9: wpisy ukryte i filtr
     * dotyczą katalogu, więc działają tak samo w liście i w drzewie, a spacja
     * z gwiazdką dotyczą **zbioru zaznaczonych**, którego drzewo nie ma.
     */
    private function character(string $raw, bool $marks = false): ScreenOutcome
    {
        return match (true) {
            $raw === '.' => $this->toggleHidden(),
            $raw === '/' => $this->openFilter(),
            $marks && $raw === self::MARK_KEY => $this->toggleMark(),
            $marks && $raw === self::INVERT_KEY => $this->invertMarks(),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * `Shift`+klawisz nazwany — trzeci modyfikator słownika, w tym ekranie
     * z dwiema czynnościami (krok 44).
     *
     * `Shift`+`F8`/`Shift`+`Delete` to **druga droga usunięcia**: zawsze ta,
     * której nie robi klawisz goły (D81, nr 2). `Shift`+strzałki to zaznaczanie
     * zakresem (D81, nr 12) — czyli spacja bez podnoszenia palca — i należy do
     * **listy**, jak każde zaznaczanie: w drzewie nie robi nic (krok 43,
     * rozstrzygnięcie 9).
     */
    private function shifted(KeyPress $key): ScreenOutcome
    {
        if ($key->key === Key::F8 || $key->key === Key::Delete) {
            return $this->trash->deletePrompt(other: true);
        }

        if ($this->queries->showsTree()) {
            return ScreenOutcome::stay();
        }

        return match ($key->key) {
            Key::ArrowUp => $this->markStep(up: true),
            Key::ArrowDown => $this->markStep(up: false),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * Spacja: przełącza zaznaczenie wpisu pod kursorem i **schodzi wiersz niżej**
     * (rozstrzygnięcie 2).
     *
     * Przesunięcie jest klasyką menadżerów plików i nie jest ozdobą: zaznaczenie
     * ciągu wpisów idzie jednym palcem, bez naprzemiennego sięgania po strzałkę.
     * Na ostatnim wierszu kursor zostaje, bo `moveSelectionDown()` zatrzymuje się
     * na krańcu listy zamiast zawijać — czyli spacja przyciśnięta na końcu
     * przełącza ten sam wpis raz za razem, dokładnie jak w mc.
     *
     * Od kroku 44 spacja jest szczególnym przypadkiem kroku zaznaczania:
     * `Shift`+`↓` robi dokładnie to samo, a `Shift`+`↑` to samo w górę —
     * dokładnie jak w Far i Total Commanderze.
     */
    private function toggleMark(): ScreenOutcome
    {
        return $this->markStep(up: false);
    }

    /** Krok zaznaczania: przełącz wpis pod kursorem i przesuń kursor. */
    private function markStep(bool $up): ScreenOutcome
    {
        $pane = $this->panes->focused();

        if (!$pane->toggleMark()) {
            return ScreenOutcome::stay();
        }

        // Zaznaczenie, a nie ruch kursora, choć kursor zaraz zejdzie wiersz niżej:
        // jedno naciśnięcie klawisza to jedno zdarzenie, inaczej spacja grałaby
        // dwa dźwięki naraz.
        $this->events->fire(BrowserEvent::EntryMarked);

        if ($up) {
            $this->moveSelection->up($pane->directory());
        } else {
            $this->moveSelection->down($pane->directory());
        }

        $pane->selectionChanged();

        return ScreenOutcome::stay();
    }

    /** `*`: odwrócenie zaznaczenia na liście widocznej (rozstrzygnięcie 8). */
    private function invertMarks(): ScreenOutcome
    {
        $this->panes->focused()->invertMarks();
        $this->events->fire(BrowserEvent::EntryMarked);

        return ScreenOutcome::stay();
    }

    /** Zamiana widoku panelu z ogniskiem: lista na drzewo i z powrotem (krok 31). */
    private function toggleTree(): ScreenOutcome
    {
        $this->panes->toggleTree();

        return ScreenOutcome::stay();
    }

    /**
     * Klawisze panelu pokazującego drzewo.
     *
     * Osobna gałąź, a nie dopisek do tamtej listy przypadków, bo trzy klawisze
     * znaczą tu co innego: `→` rozwija zamiast wchodzić, `←` zwija zamiast
     * wychodzić, a `Enter` zostaje przy swoim znaczeniu z całej aplikacji (P3) —
     * zatwierdza, czyli wchodzi do katalogu. `Backspace` **nie zmienia znaczenia
     * nigdy** i to jest tu ważniejsze, niż wygląda: wyjście katalog wyżej ma jedną
     * drogę niezależną od widoku.
     */
    private function inTree(KeyPress $key): ScreenOutcome
    {
        $tree = $this->queries->treeOf();

        return match (true) {
            $key->key === Key::ArrowUp => $this->treeMoved($tree, -1),
            $key->key === Key::ArrowDown => $this->treeMoved($tree, 1),
            $key->key === Key::ArrowRight => $this->treeOpened($tree),
            $key->key === Key::ArrowLeft => $this->treeClosed($tree),
            $key->key === Key::Enter => $this->treeEntered($tree),
            $key->key === Key::Backspace => $this->goUp($this->directory()),
            $key->key === Key::Escape => $this->stepBack(),
            $key->key === Key::Character => $this->character($key->raw),
            default => ScreenOutcome::stay(),
        };
    }

    /**
     * Ruch kursora po węzłach. Kontekst sesji ogłaszamy przy każdym, bo kursor
     * drzewa **jest** wskazaniem panelu — moduł opisujący plik ma pokazać węzeł,
     * na którym użytkownik stoi, a nie zaznaczenie listy sprzed przełączenia widoku.
     */
    private function treeMoved(BrowserTree $tree, int $delta): ScreenOutcome
    {
        $tree->moveBy($delta);
        $this->panes->publishFocused();
        $this->events->fire(BrowserEvent::CursorMoved);

        return ScreenOutcome::stay();
    }

    /**
     * `→` — rozwinięcie gałęzi, a na gałęzi już rozwiniętej zejście do jej
     * pierwszego dziecka.
     *
     * Limit głębokości melduje się **zdaniem**, a nie brakiem reakcji: klawisz,
     * który raz działa, a raz nie, czyta się jak usterka, a ustawienie, którego
     * skutku nie widać, jak martwy przełącznik.
     */
    private function treeOpened(BrowserTree $tree): ScreenOutcome
    {
        $node = $tree->cursorNode();

        if ($node === null || !$node->hasChildren) {
            return ScreenOutcome::stay();
        }

        if ($node->expanded) {
            $tree->focusChild($node);
            $this->panes->publishFocused();

            return ScreenOutcome::stay();
        }

        if ($tree->expand($node)) {
            return ScreenOutcome::stay();
        }

        return ScreenOutcome::stay(Message::info(
            $this->translator->plural(self::DEPTH_LIMIT_KEY, $tree->limit() ?? 0),
        ));
    }

    /**
     * `←` — zwinięcie gałęzi, na zwiniętej skok do rodzica, a na pierwszym
     * poziomie wyjście katalog wyżej.
     *
     * Trzy znaczenia jednego klawisza wyglądają na dużo, ale są jednym zdaniem
     * czytanym z góry na dół: **wróć o poziom**. Ostatnie z nich jest przy tym
     * jedynym miejscem, w którym drzewo zachowuje się dokładnie tak, jak lista —
     * bo poziom nad pierwszym leży już na dysku, a nie w drzewie.
     */
    private function treeClosed(BrowserTree $tree): ScreenOutcome
    {
        $node = $tree->cursorNode();

        if ($node !== null && $node->expanded) {
            $tree->collapse($node);
            $this->panes->publishFocused();

            return ScreenOutcome::stay();
        }

        if ($node !== null && $tree->focusParent($node)) {
            $this->panes->publishFocused();

            return ScreenOutcome::stay();
        }

        return $this->goUp($this->directory());
    }

    /**
     * `Enter` na węźle-katalogu czyni go katalogiem panelu — czyli robi to samo,
     * co w liście, tyle że wpis bierze się z dowolnego poziomu drzewa.
     *
     * Rozwinięcia zostają: są trzymane pod kluczem bezwzględnym, więc katalog
     * otwarty i porzucony wraca w tym samym stanie.
     */
    private function treeEntered(BrowserTree $tree): ScreenOutcome
    {
        $node = $tree->cursorNode();

        if ($node === null || !$node->hasChildren) {
            return ScreenOutcome::stay();
        }

        return $this->open($tree->cursorDirectory());
    }

    /**
     * Pole filtra dostaje **panel z ogniskiem**, a nie oba naraz: rozstrzygnięcie
     * ze startu kroku 30. Filtr jest widokiem na listę, a użytkownik patrzy
     * w danej chwili na jedną — zawężanie tej drugiej robiłoby porządek tam,
     * gdzie nikt nie prosił.
     */
    private function openFilter(): ScreenOutcome
    {
        return ScreenOutcome::opens(new FilterOverlay(
            $this->panes->focused(),
            $this->moveSelection,
            $this->translator,
        ));
    }

    /**
     * `Esc` zdejmuje zawężenie z listy, na której już nie ma pola — bo pole
     * zamknięto `Enter`em, a filtr został.
     *
     * Zaznaczenie zostaje tam, gdzie stoi: użytkownik doszedł do wpisu przez
     * filtr i to jest wpis, o który mu chodziło. Powrót do miejsca sprzed filtra
     * należy do `Esc` **w oknie**, i to jest cała różnica między tymi dwoma
     * `Esc`-ami.
     *
     * **Od kroku 43 warstwy są dwie i ustępują po kolei** (rozstrzygnięcie 3):
     * najpierw zawężenie, potem zbiór zaznaczonych. Kolejność jest odwrotnością
     * zakładania — filtr leży na wierzchu, bo zmienia to, co widać — a jedno
     * naciśnięcie zdejmujące oba naraz odbierałoby użytkownikowi zawężenie,
     * o które nie prosił. Przy obu pustych klawisz nie robi nic, jak przed tym
     * krokiem: przeglądarka stoi na dnie stosu, więc nie ma dokąd wracać.
     */
    private function stepBack(): ScreenOutcome
    {
        $pane = $this->panes->focused();

        if ($this->queries->filter() !== '') {
            $pane->clearFilter();

            return ScreenOutcome::stay();
        }

        // Zbiór zdejmuje się wyłącznie tam, gdzie go widać: w drzewie zaznaczenie
        // nie istnieje (rozstrzygnięcie 9), więc `Esc` nie ma tam czego czyścić
        // i zachowuje się jak przed tym krokiem.
        if (!$this->queries->marked()->isEmpty()) {
            $pane->clearMarks();
        }

        return ScreenOutcome::stay();
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
        $settings = $this->core->settings();
        $enabled = BrowserSettings::split($settings);
        $this->panes->useSplit($enabled);

        // Proporcja idzie tą samą drogą i z tego samego powodu: pozycję zmienia
        // także zakładka ustawień, a `SplitState` pomija podaną wartość
        // w trakcie przeciągania, więc jedno nie walczy z drugim (krok 55).
        $this->panes->split()->useFraction(
            SplitSetting::fraction($settings, BrowserSettings::ID, self::SPLIT_PERCENT),
        );

        return $enabled;
    }

    /** Co robi goły klawisz usuwania — dla opisów w spisie klawiszy (krok 44). */
    private function deletesToTrash(): bool
    {
        return BrowserSettings::deleteToTrash($this->core->settings());
    }

    /**
     * Czy lista pokazuje kolumny szczegółów i nazwy kolumn (krok 27).
     *
     * Obie odpowiedzi czyta się **co klatkę**, a nie zapamiętuje przy budowie
     * ekranu, i jest to ta sama zasada, co przy podziale: zmiana ustawienia ma
     * być widoczna w następnej klatce, a nie po ponownym otwarciu przeglądarki.
     */
    private function details(): bool
    {
        return BrowserSettings::details($this->core->settings());
    }

    private function columnHeader(): bool
    {
        return BrowserSettings::columnHeader($this->core->settings());
    }

    private function axis(): SplitAxis
    {
        return BrowserSettings::splitVertical($this->core->settings())
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

        // Zdarzenie ruchu kursora (krok 46) — pada przy **każdym** kroku, także
        // wtedy, gdy kursor stoi już na krańcu listy i nigdzie się nie ruszył:
        // ekran o tym nie wie, bo przesunięcie liczy przypadek użycia, a
        // odpowiedzi nie zwraca. Rzecz zostaje po stronie odbiorcy, i to jest
        // uczciwsze niż drugi rachunek tutaj — trzymany klawisz i tak daje
        // trzydzieści zdarzeń na sekundę, więc odbiorca musi mieć własny próg.
        $this->events->fire(BrowserEvent::CursorMoved);

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
        $entered = $this->navigateInto->execute($directory, $this->queries->showsHidden());

        if ($entered !== null) {
            // Zdarzenie „panel wszedł do innego katalogu" ogłasza `BrowserState`,
            // a nie ten ekran (krok 46): `enter()` jest jedyną drogą, którą katalog
            // się zmienia, a wchodzi się tędy z czterech miejsc — klawisza,
            // drzewa, `browser.jump` i `browser.open`.
            $this->panes->focused()->enter($entered);
        }

        return ScreenOutcome::stay();
    }

    private function goUp(Directory $directory): ScreenOutcome
    {
        $parent = $this->navigateUp->execute($directory, $this->queries->showsHidden());

        if ($parent !== null) {
            $this->panes->focused()->enter($parent);
        }

        return ScreenOutcome::stay();
    }

    /**
     * Widoczność wpisów ukrytych — czynność wspólna z komendą `browser.hidden`,
     * więc mieszka w `HiddenEntries`, a nie tutaj (krok 32).
     *
     * Do tamtej klasy przeniosła się razem z powodem, dla którego kolejność jej
     * kroków nie jest dowolna: odczyt obu katalogów idzie **przed** zapisem
     * konfiguracji. Ekranowi zostaje to, co do niego należy — zamiana wyniku na
     * `ScreenOutcome`.
     */
    private function toggleHidden(): ScreenOutcome
    {
        return ScreenOutcome::stay($this->hidden->flip());
    }
}
