<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Application\UseCase\MoveSelectionUseCase;
use LightManager\Module\Browser\Application\UseCase\NavigateIntoDirectoryUseCase;
use LightManager\Module\Browser\Application\UseCase\NavigateUpUseCase;
use LightManager\Module\Browser\Application\UseCase\PreviewSelectedEntryUseCase;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Presentation\Component\EntryList;
use LightManager\Module\Browser\Presentation\Component\EntryTree;
use LightManager\Module\Browser\Presentation\Component\PathLine;
use LightManager\Module\Browser\Presentation\Component\PreviewBox;
use LightManager\Module\Browser\Presentation\Overlay\FilterOverlay;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\Split;
use LightManager\Presentation\Ui\ComponentInterface;
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

    public function __construct(
        private readonly BrowserPanes $panes,
        private readonly LoopState $state,
        private readonly MoveSelectionUseCase $moveSelection,
        private readonly NavigateIntoDirectoryUseCase $navigateInto,
        private readonly NavigateUpUseCase $navigateUp,
        private readonly HiddenEntries $hidden,
        private readonly PreviewSelectedEntryUseCase $preview,
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
        return new ScreenZone('layout.zone.preview', new PreviewBox($this->preview, $this->pointed()));
    }

    /**
     * Katalog wraz z zaznaczeniem, na które panel z ogniskiem **wskazuje** — z
     * listy albo z drzewa.
     *
     * Dzięki tej jednej metodzie pas podglądu nie wie, że drzewo istnieje:
     * `BrowserTree` oddaje zwykły agregat z zaznaczeniem na węźle pod kursorem,
     * czyli dokładnie to, czym od kroku 21 jest „zaznaczony wpis”.
     */
    private function pointed(): Directory
    {
        return $this->panes->focusedDirectory();
    }

    private function suffix(): string
    {
        $suffix = '';
        $pane = $this->panes->focused();
        $directory = $pane->directory();
        $selection = $directory->selection();

        if ($this->panes->focusShowsTree()) {
            // W drzewie liczy się **węzły**, nie wpisy katalogu: numer wpisu
            // w korzeniu nie powiedziałby nic o tym, gdzie stoi kursor po
            // rozwinięciu trzech gałęzi.
            $tree = $this->panes->focusedTree();
            $index = $tree->cursorIndex();

            if ($index !== null) {
                $suffix .= sprintf('  —  %d/%d', $index + 1, $tree->count());
            }
        } elseif ($selection !== null) {
            $suffix .= sprintf('  —  %d/%d', $selection->index + 1, count($directory->entries()));
        }

        if ($pane->showsHiddenEntries()) {
            $suffix .= '  ' . $this->translator->translate(self::HIDDEN_MARKER_KEY);
        }

        if (!$pane->filter()->isEmpty()) {
            $suffix .= '  ' . $this->translator->translate(
                self::FILTER_MARKER_KEY,
                ['fragment' => $pane->filter()->value],
            );
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
            return $this->pane($this->panes->focusesSecond() ? 1 : 0, framed: false)->draw($bounds);
        }

        return (new Split($this->pane(0, framed: true), $this->pane(1, framed: true), $this->axis()))->draw($bounds);
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
        [$state, $window] = $this->panes->pane($index);

        if ($this->panes->showsTree($index)) {
            return new EntryTree($this->panes->tree($index), $this->translator, $framed, $state->filter());
        }

        return new EntryList(
            $state->directory(),
            $window,
            $this->translator,
            framed: $framed,
            details: $this->details(),
            header: $this->columnHeader(),
            filter: $state->filter(),
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

    public function bindings(): array
    {
        $bindings = $this->panes->focusShowsTree() ? $this->treeBindings() : [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move'),
            KeyBinding::of([Key::Enter, Key::ArrowRight], 'module.browser.help.open'),
            KeyBinding::of([Key::Backspace, Key::ArrowLeft], 'module.browser.help.up'),
            KeyBinding::character('.', 'module.browser.help.hidden'),
            KeyBinding::character('/', 'module.browser.help.filter'),
        ];

        $bindings[] = KeyBinding::ctrl(self::TREE_KEY, 'module.browser.help.tree');

        // Klawisz ogniska pokazujemy dopiero wtedy, gdy podział jest włączony:
        // podpowiedź o przenoszeniu ogniska między panelami, których nie ma,
        // byłaby kłamstwem — a pasek stanu i spis klawiszy mają jedno źródło.
        if ($this->splits()) {
            $bindings[] = KeyBinding::of([Key::Tab], 'module.browser.help.focus');
        }

        // Tą samą regułą: zdjęcie filtra pokazujemy dopiero wtedy, gdy jest co
        // zdejmować. `Esc` na liście bez filtra nie robi nic i nie ma prawa
        // twierdzić, że robi.
        if (!$this->panes->focused()->filter()->isEmpty()) {
            $bindings[] = KeyBinding::of([Key::Escape], 'module.browser.help.filter.clear');
        }

        return $bindings;
    }

    /**
     * Spis klawiszy panelu pokazującego drzewo.
     *
     * Strzałki poziome znaczą tu **co innego** niż w liście i to jest cały powód,
     * dla którego spis rozdziela się na dwa: w liście `→` wchodzi do katalogu,
     * w drzewie rozwija gałąź. Jedna wspólna lista musiałaby opisać oba znaczenia
     * naraz, czyli skłamać w połowie przypadków — a precedens z kroku 30 mówi, że
     * spis pokazuje wyłącznie to, co działa tu i teraz.
     *
     * @return list<KeyBinding>
     */
    private function treeBindings(): array
    {
        return [
            KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'help.key.move'),
            KeyBinding::of([Key::ArrowRight], 'module.browser.help.tree.expand'),
            KeyBinding::of([Key::ArrowLeft], 'module.browser.help.tree.collapse'),
            KeyBinding::of([Key::Enter], 'module.browser.help.open'),
            KeyBinding::of([Key::Backspace], 'module.browser.help.up'),
            KeyBinding::character('.', 'module.browser.help.hidden'),
            KeyBinding::character('/', 'module.browser.help.filter'),
        ];
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        // Litera z `Ctrl` nie jest treścią (reguła 11j) i nie jest też klawiszem
        // listy: albo przełącza widok panelu, albo nie znaczy w tym ekranie nic.
        if ($key->key === Key::Character && $key->ctrl) {
            return $key->raw === self::TREE_KEY ? $this->toggleTree() : ScreenOutcome::stay();
        }

        if ($key->key === Key::Tab) {
            return $this->moveFocus();
        }

        if ($this->panes->focusShowsTree()) {
            return $this->inTree($key);
        }

        $directory = $this->panes->focused()->directory();

        return match (true) {
            $key->key === Key::ArrowUp => $this->moved($directory, up: true),
            $key->key === Key::ArrowDown => $this->moved($directory, up: false),
            $key->key === Key::Enter, $key->key === Key::ArrowRight => $this->open($directory),
            $key->key === Key::Backspace, $key->key === Key::ArrowLeft => $this->goUp($directory),
            $key->key === Key::Escape => $this->dropFilter(),
            // Litera z modyfikatorem nie jest treścią (reguła 11j): goła kropka
            // przełącza wpisy ukryte, `Ctrl`+`.` i `Alt`+`.` — nie. `Ctrl` odpadł
            // już wyżej, razem z klawiszem widoku, więc zostaje tu sam `Alt`.
            $key->key === Key::Character && !$key->alt => $this->character($key->raw),
            default => ScreenOutcome::stay(),
        };
    }

    private function character(string $raw): ScreenOutcome
    {
        return match ($raw) {
            '.' => $this->toggleHidden(),
            '/' => $this->openFilter(),
            default => ScreenOutcome::stay(),
        };
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
        $tree = $this->panes->focusedTree();

        return match (true) {
            $key->key === Key::ArrowUp => $this->treeMoved($tree, -1),
            $key->key === Key::ArrowDown => $this->treeMoved($tree, 1),
            $key->key === Key::ArrowRight => $this->treeOpened($tree),
            $key->key === Key::ArrowLeft => $this->treeClosed($tree),
            $key->key === Key::Enter => $this->treeEntered($tree),
            $key->key === Key::Backspace => $this->goUp($this->panes->focused()->directory()),
            $key->key === Key::Escape => $this->dropFilter(),
            $key->key === Key::Character && !$key->alt => $this->character($key->raw),
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

        return $this->goUp($this->panes->focused()->directory());
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
     */
    private function dropFilter(): ScreenOutcome
    {
        $pane = $this->panes->focused();

        if (!$pane->filter()->isEmpty()) {
            $pane->clearFilter();
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
        $enabled = BrowserSettings::split($this->state->settings());
        $this->panes->useSplit($enabled);

        return $enabled;
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
        return BrowserSettings::details($this->state->settings());
    }

    private function columnHeader(): bool
    {
        return BrowserSettings::columnHeader($this->state->settings());
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
