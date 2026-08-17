<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Ui\Frame;
use LightManager\Application\Ui\FrameText;
use LightManager\Application\Ui\Plane;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Presentation\Ui\Component\Align;
use LightManager\Presentation\Ui\Component\Button;
use LightManager\Presentation\Ui\Component\Choice;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\Component\ImageBox;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\Component\Marquee;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\ProgressBar;
use LightManager\Presentation\Ui\Component\Section;
use LightManager\Presentation\Ui\Component\SectionList;
use LightManager\Presentation\Ui\Component\Spacer;
use LightManager\Presentation\Ui\Component\Split;
use LightManager\Presentation\Ui\Component\StatusBar;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\Component\Tabs;
use LightManager\Presentation\Ui\Component\TextInput;
use LightManager\Presentation\Ui\Component\TextSpan;
use LightManager\Presentation\Ui\Component\TextView;
use LightManager\Presentation\Ui\Component\Toggle;
use LightManager\Presentation\Ui\Component\TreeNode;
use LightManager\Presentation\Ui\Component\TreeView;
use LightManager\Presentation\Ui\ComponentInterface;
use LightManager\Presentation\Ui\Container\Slot;
use LightManager\Presentation\Ui\Container\VStack;
use LightManager\Presentation\Ui\Hint;
use LightManager\Presentation\Ui\HudLayout;
use LightManager\Presentation\Ui\SplitAxis;
use LightManager\Presentation\Ui\StatusHints;

/**
 * Buduje treść mierzonych klatek.
 *
 * Trzy reguły rządzą tą klasą:
 *
 * 1. **Determinizm.** Ta sama konfiguracja daje bajt w bajt tę samą klatkę przy
 *    każdym uruchomieniu — nazwy wpisów pochodzą z licznika, nie z katalogu na
 *    dysku ani z generatora losowego. Bez tego porównanie z wzorcem nie znaczy
 *    nic.
 * 2. **Wierność.** Od kroku 18 klatka powstaje z **tych samych komponentów**, co
 *    w aplikacji: `ListView`, `Panel`, `StatusBar`, `ImageBox`. Wcześniej
 *    scenariusz składał wiersze własnym kodem i musiał nadążać za zmianami
 *    w produkcji; teraz nadąża sam.
 * 3. **Rozdzielność.** Scenariusze bez chromu dostają **pustą płaszczyznę
 *    spodnią**, a nie panele — dzięki temu różnica wobec scenariuszy z chromem
 *    jest dokładnie jego kosztem.
 *
 * Płaszczyzna spodnia jest zawsze osobna i zawsze pierwsza, także gdy jest
 * pusta. To nie kosmetyka: enkoder zapamiętuje ją między klatkami, więc treść
 * położona na niej byłaby mierzona raz, a potem podawana z pamięci — i pomiar
 * pokazywałby zero.
 *
 * **Treść mierzonej klatki nie przechodzi przez katalog napisów** — i to nie
 * przeoczenie, tylko warunek poprawności pomiaru (D33). Napisy nie są tu
 * interfejsem, lecz obciążeniem: liczy się ich długość w znakach, bo to ona
 * kosztuje przy rasteryzacji.
 */
final class ScenarioFactory
{
    private const SAMPLE_PATH = '/home/uzytkownik/projekty/light-manager/src/Infrastructure';

    /**
     * Stopka mierzonej klatki — **taka, jaką aplikacja naprawdę rysuje** od kroku
     * 40: klawisze panelu z ogniskiem wraz z nazwą miejsca, potem klawisze ekranu,
     * na końcu globalne i skrót modułu.
     *
     * Do kroku 40 stała tu `'F1 pomoc · F2 ustawienia · F10 wyjście'` — trzy
     * pozycje, których aplikacja nie miała już wtedy (rdzeń ma pięć klawiszy od
     * kroku 32). Scenariusze mierzyły więc stopkę krótszą od prawdziwej, a zmiana
     * jej treści nie zmieniała w pomiarze nic. Napisy są nietłumaczone — to jawny
     * wyjątek D33: liczy się ich **długość w znakach**, bo to ona kosztuje przy
     * rasteryzacji.
     *
     * @var list<string>
     */
    private const HINTS = [
        'Lista: ↑ / ↓ zaznaczenie',
        'Enter / → katalog',
        'Backspace / ← wyżej',
        // Dwie pozycje dołożone w kroku 43 i dołożone **z tego samego powodu**,
        // dla którego krok 40 przepisał tę stałą w całości: stopka mierzona ma
        // być stopką, którą aplikacja naprawdę rysuje. Obie należą do **miejsca**
        // (listy), więc stoją zaraz za klawiszami kursora, a nie na końcu.
        'Space zaznacz',
        '* odwróć',
        '. ukryte',
        '/ filtr',
        'Ctrl+T drzewo',
        'F1 pomoc',
        'F2 ustawienia',
        'F9 menu',
        'F12 komendy',
        'F10 wyjście',
        'Ctrl+D Opis pliku',
    ];

    /** Która pozycja stopki jest przypięta — `F1`, jak w aplikacji. */
    private const PINNED_HINT = 6;

    private const MESSAGE = '· Pomiar wydajności potoku renderowania.';

    private const POPUP_LINES = 6;

    /** Tyle podpowiedzi widać w oknie komend przy typowym wpisaniu przedrostka. */
    private const COMMAND_SUGGESTIONS = 5;

    /**
     * Długość dopasowania w scenariuszu `highlight` — trzy znaki, jak wpisane
     * trzy litery.
     *
     * Zakres jest **stały**, a nie wyszukany w nazwie, i to jest świadome:
     * scenariusz mierzy rysowanie, nie szukanie, a fragment znaleziony w treści
     * przestawałby trafiać w każdy wiersz przy okrągłej setce wierszy okna
     * (numer wpisu zmienia wtedy dwie pierwsze cyfry). Pomiar, który po cichu
     * słabnie wraz z wysokością terminala, jest gorszy niż brak pomiaru.
     */
    private const HIGHLIGHT_CHARACTERS = 3;

    /**
     * Znak zaznaczenia w scenariuszu `marked` — **przepisany z katalogu napisów
     * przeglądarki**, nie wzięty stamtąd.
     *
     * To ta sama zasada, co przy szerokościach kolumn kilka metod niżej: treść
     * klatki pomiarowej jest nietłumaczona (D33), bo długość napisu w znakach
     * wchodzi do wyniku, a wzorzec ma się zgadzać bajt w bajt niezależnie od
     * ustawionego języka. Znak spoza ASCII jest tu przy tym częścią pomiaru:
     * rasteryzuje się osobno i osobno ląduje w pamięci podręcznej wierszy.
     */
    private const MARK = '•';

    /**
     * Ile wierszy obejmuje zaznaczenie w scenariuszu `marquee` (krok 56).
     *
     * Pięć, bo tyle mówi miara kroku („przeciągnięcie przez pięć wierszy listy”)
     * i tyle bierze jedno przeciągnięcie ręką. Prostokąt na całą klatkę byłby
     * sufitem, ale mierzyłby przemalowanie okna zamiast czynności, którą
     * ktokolwiek wykonuje.
     */
    private const MARQUEE_ROWS = 5;

    /**
     * Zakładki ekranu ustawień — dwie rdzenia, spis modułów i po jednej na
     * moduł, czyli tyle, ile widzi użytkownik przy obu modułach włączonych.
     *
     * @var list<string>
     */
    private const SETTINGS_TABS = ['Wygląd', 'Grafika', 'Moduły', 'przeglądarka', 'opis pliku'];

    /** @var list<string> */
    private const SETTINGS_LABELS = [
        'Język interfejsu',
        'Motyw graficzny',
        'Wygładzanie tekstu',
        'Kolory palety Sixela',
        'Wygładzanie obrysów',
        'Szerokość okna w kolumnach',
        'Zawijanie długich wierszy',
        'Wysokość okna w wierszach',
    ];

    /** @var list<string> */
    private const SETTINGS_VALUES = ['polski', 'Grafit', '64', '100', 'nordyk', '30', 'browser', 'papier'];

    /**
     * Ile poziomów schodzi drzewo w scenariuszu `tree` i ile węzłów ma każdy.
     *
     * Cztery poziomy po trzy węzły dają dwanaście wierszy na gałąź — tyle, żeby
     * w panelu stanęły obok siebie wcięcia od zerowego do trzeciego. Głębiej
     * scenariusz nie schodzi, bo mierzyłby wtedy skracanie wcięcia
     * (`TreeView::MINIMUM_LABEL`), a nie samo wcięcie.
     */
    private const TREE_LEVELS = 4;

    private const TREE_CHILDREN = 3;

    public function __construct(
        private readonly BenchmarkOptions $options,
        /** Ścieżka obrazu do scenariusza z miniaturą; `null` wyłącza podgląd. */
        private readonly ?string $imagePath = null,
    ) {
    }

    public function build(Scenario $scenario): ScenarioFrame
    {
        $rows = max(1, $this->options->rows);
        $columns = max(1, $this->options->columns);
        $layout = new HudLayout(
            $rows,
            $columns,
            // Pytanie zadane dokładnie tak, jak zadaje je `FrameComposer` — inaczej
            // scenariusz mierzyłby pasek jednowierszowy, a aplikacja rysowała
            // dwuwierszowy.
            wideStatus: !self::hints()->fitInOneRow(
                StatusBar::hintColumns(HudLayout::contentColumns($columns), self::MESSAGE),
            ),
        );
        $window = new Rect(0, 0, $rows, $columns);

        $planes = [new Plane('chrome', $window, $this->chrome($scenario, $layout))];
        $planes[] = new Plane('content', $window, $this->content($scenario, $layout));

        if ($scenario === Scenario::Popup) {
            $planes[] = $this->modal($rows, $columns);
        }

        if ($scenario === Scenario::Command) {
            $planes[] = $this->commandWindow($layout, $rows, $columns);
        }

        if ($scenario === Scenario::Marquee) {
            $planes[] = $this->marquee($planes, $layout, $rows, $columns);
        }

        return new ScenarioFrame(new Frame($planes), $rows, $columns);
    }

    /**
     * Czwarta płaszczyzna: **prostokąt zaznaczony wskaźnikiem** (krok 56).
     *
     * Powstaje tą samą drogą, co w aplikacji — warstwa tekstowa z płaszczyzn już
     * złożonych, a na niej `Marquee` — bo scenariusz, który składałby ją własnym
     * kodem, mierzyłby swój kod, a nie ten z `FrameComposer`a (reguła 2 tej
     * klasy: wierność).
     *
     * @param list<Plane> $planes płaszczyzny złożone do tej pory
     */
    private function marquee(array $planes, HudLayout $layout, int $rows, int $columns): Plane
    {
        $list = HudLayout::contentOf($layout->list, $layout->listIsPanel());
        $area = $list->rowsFrom(0, min(self::MARQUEE_ROWS, $list->rows));
        $text = FrameText::of(new Frame($planes), $rows, $columns);

        return new Plane('selection', $area, Marquee::over($text, $area));
    }

    /** @return list<Primitive> */
    private function chrome(Scenario $scenario, HudLayout $layout): array
    {
        if (!$scenario->needsChrome()) {
            return [];
        }

        $primitives = [];
        $panels = [
            [$layout->header, $layout->headerIsPanel(), 'PATH'],
            [$layout->list, $layout->listIsPanel(), 'FILES'],
            [$layout->status, $layout->statusIsPanel(), ''],
        ];

        foreach ($panels as [$zone, $isPanel, $label]) {
            // Klatka podzielona ma w miejscu strefy środkowej **dwie** obwódki
            // zamiast jednej — i tak samo jak jedna, leżą one w płaszczyźnie
            // spodniej, bo między klatkami się nie zmieniają.
            // Miniatura od kroku 47 mierzy się tam, gdzie aplikacja ją rysuje:
            // w **prawym panelu podziału**, czyli w klatce modułu opisu pliku
            // (`PreviewPane`). Pas podglądu, w którym stała do tej pory, wyszedł
            // z kontraktu ekranu wraz z `preview()` (D76, D78).
            if (($scenario === Scenario::Split || $scenario === Scenario::Thumbnail) && $label === 'FILES') {
                foreach ($this->splitFrames($layout) as $primitive) {
                    $primitives[] = $primitive;
                }

                continue;
            }

            if ($isPanel) {
                foreach ((new Panel($label))->draw($zone) as $primitive) {
                    $primitives[] = $primitive;
                }
            }
        }

        return $primitives;
    }

    /** @return list<Primitive> */
    private function content(Scenario $scenario, HudLayout $layout): array
    {
        $list = $scenario->needsChrome()
            ? HudLayout::contentOf($layout->list, $layout->listIsPanel())
            : new Rect(0, Panel::CONTENT_COLUMN, $layout->list->rows + $layout->header->rows, $layout->list->columns - 2 * Panel::CONTENT_COLUMN);

        return match ($scenario) {
            Scenario::Empty, Scenario::Chrome => [],
            Scenario::Text => $this->list($list, selected: null, scroll: null),
            Scenario::Selection => $this->list($list, selected: 0, scroll: null, everyRowSelected: true),
            Scenario::Scrollbar => $this->list($list, selected: null, scroll: $this->scroll($list->rows)),
            Scenario::Sections => $this->sections($list),
            Scenario::Progress => $this->progress($list),
            Scenario::Columns => $this->columns($list),
            Scenario::Highlight => $this->columns($list, highlighted: true),
            Scenario::Marked => $this->columns($list, marked: true),
            Scenario::TextView => $this->textView($list),
            Scenario::Tree => $this->tree($list),
            Scenario::Split => $this->splitLists($layout),
            Scenario::Settings => $this->settingsScreen($layout, $list),
            default => $this->fullContent($layout, $list, $scenario),
        };
    }

    /**
     * Lista sekcji wypełniająca panel: co trzecia zwinięta, kursor na drugiej.
     *
     * Proporcja jest umyślna. Same sekcje rozwinięte mierzyłyby to samo, co
     * `chrome-text`, plus kilka nagłówków; same zwinięte — prawie nic. Mieszanka
     * odpowiada temu, jak lista wygląda po chwili używania, i pokazuje **oba**
     * znaczniki naraz.
     *
     * @return list<Primitive>
     */
    private function sections(Rect $bounds): array
    {
        $sections = [];
        $index = 0;

        while (SectionList::rowCount($sections) < max(1, $bounds->rows)) {
            $collapsed = $index % 3 === 2;
            $sections[] = new Section(
                'sekcja-' . $index,
                sprintf('SEKCJA %02d', $index),
                $collapsed ? [] : $this->sectionRows($index),
                $collapsed,
            );

            ++$index;
        }

        return (new SectionList($sections, 0, 1, $this->scroll($bounds->rows)))->draw($bounds);
    }

    /**
     * Obwódki obu paneli — tak, jak oddaje je przeglądarka po włączeniu podziału.
     *
     * Panel lewy jest czynny (nawiasy i etykieta w akcencie), prawy nie. Obie
     * obwódki idą na płaszczyznę **spodnią**, bo dokładnie tam kładzie je
     * `FrameComposer`: obrys z wygładzaniem kosztuje kilkanaście milisekund, więc
     * rysowany co klatkę zjadałby połowę budżetu (zmierzone w kroku 24).
     *
     * @return list<Primitive>
     */
    private function splitFrames(HudLayout $layout): array
    {
        $primitives = [];
        $index = 0;

        foreach (Split::halves($layout->list, SplitAxis::Vertical) as $bounds) {
            $focused = $index === 0;
            $panel = new Panel(
                Label::fitEnd(self::SAMPLE_PATH . ($focused ? '' : '/Imagick'), Panel::labelRoom($bounds)),
                Role::Border,
                $focused ? Role::Accent : Role::Border,
                $focused ? Role::Accent : Role::Muted,
            );

            foreach ($panel->draw($bounds) as $primitive) {
                $primitives[] = $primitive;
            }

            ++$index;
        }

        return $primitives;
    }

    /**
     * Treść obu paneli: dwie listy plików, każda we wnętrzu swojej obwódki.
     * W panelu czynnym stoi zaznaczenie, w nieczynnym nie ma go wcale.
     *
     * @return list<Primitive>
     */
    private function splitLists(HudLayout $layout): array
    {
        $primitives = [];
        $index = 0;

        foreach (Split::halves($layout->list, SplitAxis::Vertical) as $bounds) {
            $inner = Panel::inner($bounds);

            foreach ($this->list($inner, $index === 0 ? 2 : null, $this->scroll($inner->rows)) as $primitive) {
                $primitives[] = $primitive;
            }

            ++$index;
        }

        return $primitives;
    }

    /**
     * Panel wypełniony paskami postępu: co czwarty w trybie „postęp nieznany”,
     * reszta z wypełnieniem rosnącym co wiersz.
     *
     * Dwie rzeczy są tu umyślne. **Chwila jest podana z wiersza**, a nie
     * z zegara — wędrujące wypełnienie musi w każdym przebiegu stanąć w tym
     * samym miejscu, inaczej klatka przestałaby być powtarzalna i porównanie
     * z wzorcem nie znaczyłoby nic. **Tłumacza nie ma**, więc liczba procent
     * idzie w postaci surowej: długość napisu w znakach wchodzi do wyniku, a ta
     * nie może zależeć od wybranego języka (D33).
     *
     * @return list<Primitive>
     */
    private function progress(Rect $bounds): array
    {
        $primitives = [];

        for ($row = 0; $row < $bounds->rows; ++$row) {
            $unknown = $row % 4 === 3;
            $bar = new ProgressBar(
                $unknown ? null : ($row % 21) / 20,
                $unknown ? 'licze rozmiar katalogu' : 'sha256 pliku obraz-' . sprintf('%02d', $row) . '.jpg',
                0.31 * $row,
            );

            foreach ($bar->draw($bounds->line($row)) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        return $primitives;
    }

    /**
     * Drzewo wypełniające panel: gałęzie rozwinięte do czterech poziomów, kursor
     * na węźle z drugiego.
     *
     * Kursor stoi **głębiej niż na pierwszym poziomie** i nie jest to szczegół:
     * pasek zaznaczenia idzie na cały wiersz, więc leżąc na wierszu z wcięciem,
     * mierzy razem z nim tę samą rzecz, co w aplikacji.
     *
     * @return list<Primitive>
     */
    private function tree(Rect $bounds): array
    {
        $nodes = [];
        $group = 0;

        while (count($nodes) < max(1, $bounds->rows)) {
            $this->branch($nodes, [], $group, 0);
            ++$group;
        }

        return (new TreeView($nodes, 0, 1, $this->scroll($bounds->rows)))->draw($bounds);
    }

    /**
     * Jedna gałąź: katalog rozwinięty, katalog zwinięty i plik — czyli wszystkie
     * trzy kształty wiersza naraz, i tak na każdym poziomie.
     *
     * Pierwsze dziecko rozwija się dalej, więc na jego poziomie biegnie pionowa
     * prowadnica (`│`) aż do ostatniego rodzeństwa. Bez tego układu scenariusz
     * mierzyłby samo wcięcie spacjami, czyli rzecz, która nie kosztuje nic.
     *
     * @param list<TreeNode> $nodes
     * @param list<bool>     $guides
     */
    private function branch(array &$nodes, array $guides, int $group, int $level): void
    {
        for ($index = 0; $index < self::TREE_CHILDREN; ++$index) {
            $last = $index === self::TREE_CHILDREN - 1;
            $file = $last;
            $expanded = $index === 0 && $level < self::TREE_LEVELS - 1;

            $nodes[] = new TreeNode(
                sprintf('%02d-%d-%d', $group, $level, $index),
                $file
                    ? sprintf('plik-%02d-%02d.txt', $group, $index)
                    : sprintf('katalog-%02d-%02d/', $group, $index),
                $guides,
                $last,
                !$file,
                $expanded,
                $file ? sprintf('%d,%d kB', 1 + $index, $group % 10) : '',
                $file ? Role::Text : Role::Accent,
            );

            if ($expanded) {
                // Rozwija się **pierwszy** z trojga rodzeństwa, więc pod nim
                // zawsze zostają jeszcze dwa — i prowadnica na jego poziomie
                // biegnie przez całe poddrzewo. Stąd `true` wprost, a nie `!$last`.
                $this->branch($nodes, [...$guides, true], $group, $level + 1);
            }
        }
    }

    /** @return list<ListRow> */
    private function sectionRows(int $section): array
    {
        $rows = [];

        for ($index = 0; $index < 4; ++$index) {
            $rows[] = new ListRow(
                sprintf('wlasciwosc-%02d-%02d', $section, $index),
                sprintf('%d,%d kB', 1 + $index % 900, $index % 10),
            );
        }

        return $rows;
    }

    /**
     * Pełna klatka przeglądarki: ścieżka, lista z zaznaczeniem i suwakiem,
     * komunikat i podpowiedzi, a przy scenariuszu z miniaturą — **podzielona
     * klatka opisu pliku**: lista po lewej, obraz po prawej.
     *
     * @return list<Primitive>
     */
    private function fullContent(HudLayout $layout, Rect $list, Scenario $scenario): array
    {
        $primitives = $this->pathLine($layout);

        if ($scenario === Scenario::Thumbnail) {
            [$left, $right] = Split::halves($layout->list, SplitAxis::Vertical);
            $listBounds = Panel::inner($left);

            foreach ($this->list($listBounds, selected: 2, scroll: $this->scroll($listBounds->rows)) as $primitive) {
                $primitives[] = $primitive;
            }

            $box = new ImageBox($this->imagePath, '1600 × 1200 · JPEG · 412,3 kB');

            foreach ($box->draw(Panel::inner($right)) as $primitive) {
                $primitives[] = $primitive;
            }

            foreach ($this->statusLine($layout) as $primitive) {
                $primitives[] = $primitive;
            }

            return $primitives;
        }

        foreach ($this->list($list, selected: 2, scroll: $this->scroll($list->rows)) as $primitive) {
            $primitives[] = $primitive;
        }

        foreach ($this->statusLine($layout) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    /**
     * Wiersz ścieżki w strefie górnej — wspólny dla klatek pełnych.
     *
     * Wydzielony w kroku 38, żeby scenariusz `settings` mógł mieć strefy skrajne
     * **co do prymitywu takie same** jak `chrome-text`: para rozlicza się tylko
     * wtedy, gdy różni je wyłącznie to, co się mierzy.
     *
     * @return list<Primitive>
     */
    private function pathLine(HudLayout $layout): array
    {
        return (new Label(self::SAMPLE_PATH . '  —  12/240'))
            ->draw(HudLayout::contentOf($layout->header, $layout->headerIsPanel()));
    }

    /** @return list<Primitive> */
    private function statusLine(HudLayout $layout): array
    {
        return (new StatusBar(self::MESSAGE, Role::Info, self::hints()))
            ->draw(HudLayout::contentOf($layout->status, $layout->statusIsPanel()));
    }

    /**
     * Podpowiedzi mierzonej klatki, złożone z gotowych napisów.
     *
     * Aplikacja składa je z `KeyBinding` i katalogu napisów; tu przychodzą wprost,
     * bo pomiar nie ma ekranu, który by je zadeklarował — a rachunek ustępowania
     * i pakowania w wiersze jest **ten sam**, bo robi go ta sama klasa.
     */
    private static function hints(): StatusHints
    {
        $items = [];

        foreach (self::HINTS as $index => $text) {
            $items[] = new Hint($text, $index === self::PINNED_HINT);
        }

        return new StatusHints($items);
    }

    /**
     * Pełna klatka ekranu ustawień: zakładki, pozycje, wiersz czynności — w tym
     * samym chromie i z tymi samymi strefami skrajnymi, co `chrome-text`
     * (krok 38).
     *
     * @return list<Primitive>
     */
    private function settingsScreen(HudLayout $layout, Rect $list): array
    {
        $primitives = $this->pathLine($layout);

        foreach ($this->settingsRows($list) as $primitive) {
            $primitives[] = $primitive;
        }

        foreach ($this->statusLine($layout) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
    }

    /**
     * Treść zakładki: pasek zakładek, odstęp, pozycje wypełniające panel, odstęp
     * i wiersz czynności — złożone tym samym `VStack`iem, co w `SettingsScreen`.
     *
     * Kursor stoi na **pozycji**, a nie na pasku zakładek, bo tak wygląda ekran
     * przez większość czasu, a pasek kursora pod wierszem jest tym, co w tej
     * klatce kosztuje.
     *
     * @return list<Primitive>
     */
    private function settingsRows(Rect $bounds): array
    {
        $slots = [
            Slot::fixed(new Tabs(self::SETTINGS_TABS, 1, false), 1),
            Slot::fixed(new Spacer(), 1),
        ];

        // Cztery wiersze zabierają: pasek zakładek, dwa odstępy i wiersz
        // czynności — reszta panelu należy do pozycji.
        $positions = max(1, $bounds->rows - 4);

        for ($index = 0; $index < $positions; ++$index) {
            $slots[] = Slot::fixed($this->settingsPosition($index, $index === 2), 1);
        }

        $slots[] = Slot::fixed(new Spacer(), 1);
        $slots[] = Slot::fixed(
            new Button('Przywróć ustawienia domyślne', static function (): void {
            }, 'help.key.restore'),
            1,
        );

        return (new VStack($slots))->draw($bounds);
    }

    /**
     * Pojedyncza pozycja zakładki: co trzecia przełączana, reszta wybierana
     * z listy wartości.
     *
     * Etykieta niesie numer pozycji i to nie jest ozdoba: pamięć podręczna
     * wierszy (D34) buduje klucz z treści, więc osiem powtórzonych etykiet
     * dałoby osiem trafień i pomiar pokazałby koszt jednego wiersza zamiast
     * kosztu panelu.
     */
    private function settingsPosition(int $index, bool $selected): ComponentInterface
    {
        $label = sprintf('%s %02d', self::SETTINGS_LABELS[$index % count(self::SETTINGS_LABELS)], $index);

        if ($index % 3 === 2) {
            return new Toggle($label, $index % 2 === 0, 'tak', 'nie', $selected);
        }

        return new Choice($label, self::SETTINGS_VALUES[$index % count(self::SETTINGS_VALUES)], $selected);
    }

    /**
     * Lista o czterech kolumnach — dokładnie takich, jakie od kroku 27 pokazuje
     * przeglądarka plików: nazwa, rozmiar, data zmiany i prawa dostępu.
     *
     * Szerokości i kolejność ustępowania są **przepisane z `EntryList`**, a nie
     * wymyślone na potrzeby pomiaru. Gdyby się rozjechały, scenariusz mierzyłby
     * tabelę, której nikt nie ogląda — a to jest dokładnie ten rodzaj pomiaru,
     * który wygląda na wiarygodny i nie znaczy nic.
     *
     * Data jest **stała dla wszystkich wierszy**, jak wszystko w tym narzędziu:
     * ta sama konfiguracja ma dawać bajt w bajt tę samą klatkę, a `date()` bez
     * podanego znacznika czasu robiłaby z pomiaru ruchomy cel.
     *
     * Trzy scenariusze na jednym rachunku i to jest cała ich konstrukcja:
     * `columns` bez niczego, `highlight` z dopasowaniem filtra, `marked`
     * z zaznaczeniem wielokrotnym (krok 43). Rozliczają się parami z pierwszym,
     * więc muszą różnić się **wyłącznie** tym, co mierzą — a jedyną gwarancją
     * tego jest wspólny kod, nie wspólna intencja.
     *
     * @param bool $highlighted czy każdy wiersz niesie dopasowanie filtra
     * @param bool $marked      czy lista ma kolumnę znacznika, a co trzeci wiersz
     *                          — drugą rolę napisu
     *
     * @return list<Primitive>
     */
    private function columns(Rect $bounds, bool $highlighted = false, bool $marked = false): array
    {
        $rows = [];
        $count = max(0, $bounds->rows);
        $nameColumn = $marked ? 1 : 0;

        for ($index = 0; $index < $count; ++$index) {
            $directory = $index % 6 === 0;
            $name = sprintf('%s-%04d%s', $directory ? 'katalog' : 'plik', $index, $directory ? '/' : '.txt');
            // Rytm zaznaczeń **nie może się pokrywać z rytmem katalogów** i to
            // jest cała treść tej siódemki. Przy „co trzeciej” pozycji zaznaczony
            // wypadał **każdy** katalog (bo 6 dzieli się przez 3), więc wzorzec
            // nie pokazywał ani jednego katalogu niezaznaczonego — czyli nie dało
            // się z niego odczytać, czy rola zaznaczenia odróżnia się od akcentu.
            // Trzy z siedmiu trzyma udział blisko jednej trzeciej i daje w klatce
            // wszystkie cztery kombinacje: plik i katalog, zaznaczony i nie.
            $isMarked = $marked && $index % 7 < 3;
            $cells = [
                $name,
                $directory ? '' : sprintf('%d,%d kB', 1 + $index % 900, $index % 10),
                sprintf('2026-%02d-%02d %02d:%02d', 1 + $index % 12, 1 + $index % 28, $index % 24, $index % 60),
                $index % 3 === 0 ? 'rwxr-xr-x' : 'rw-r--r--',
            ];

            $rows[] = new TableRow(
                $marked ? [$isMarked ? self::MARK : '', ...$cells] : $cells,
                match (true) {
                    $isMarked => Role::Marked,
                    $directory => Role::Accent,
                    default => Role::Text,
                },
                $highlighted ? [$nameColumn => [new TextSpan(0, self::HIGHLIGHT_CHARACTERS)]] : [],
            );
        }

        $columns = [
            Column::flexible(20),
            Column::fixed(9, yieldOrder: 3, align: Align::Right, role: Role::Muted),
            Column::fixed(17, yieldOrder: 2, role: Role::Muted),
            Column::fixed(9, yieldOrder: 1, role: Role::Muted),
        ];

        return (new Table(
            $marked ? [Column::fixed(2, yieldOrder: 4), ...$columns] : $columns,
            $rows,
            2,
            $this->scroll($bounds->rows),
        ))->draw($bounds);
    }

    /**
     * Panel wypełniony treścią pliku tekstowego — wiersze kodu o zmiennej
     * długości, część z nich dłuższa od panelu (krok 29).
     *
     * Proporcja jest umyślna, jak w scenariuszu sekcji: same wiersze krótkie
     * mierzyłyby to, co `chrome-text` z jednym napisem zamiast dwóch, a same
     * długie — wyłącznie zawijanie. Mieszanka odpowiada temu, jak wygląda kod:
     * większość wierszy mieści się w panelu, co kilka wystaje i zawija się na
     * następny, a jeden na kilkanaście jest tak długi, że zostaje przycięty do
     * jednej linijki.
     *
     * @return list<Primitive>
     */
    private function textView(Rect $bounds): array
    {
        $lines = [];
        $count = max(1, $bounds->rows);
        $width = max(1, $bounds->columns);

        for ($index = 0; $index < $count; ++$index) {
            $indent = str_repeat(' ', 4 * ($index % 4));
            $lines[] = match ($index % 8) {
                7 => $indent . str_repeat('"wartosc-' . $index . '", ', $width),
                3, 5 => $indent . sprintf('$wynik[%d] = $this->policz($wpis, $index, %d) ?? null;', $index, $index),
                default => $indent . sprintf('public function metoda%02d(): string', $index),
            };
        }

        return (new TextView($lines, wrap: true, position: $this->scroll($bounds->rows)))->draw($bounds);
    }

    /**
     * Wiersze wypełniające listę po brzegi. Co szósty wpis jest katalogiem —
     * w prawdziwej klatce proporcja jest podobna, a kolor katalogu kosztuje
     * osobną bitmapę w pamięci podręcznej.
     *
     * @return list<Primitive>
     */
    private function list(Rect $bounds, ?int $selected, ?ScrollPosition $scroll, bool $everyRowSelected = false): array
    {
        $rows = [];
        $count = max(0, $bounds->rows);

        for ($index = 0; $index < $count; ++$index) {
            $directory = $index % 6 === 0;
            $rows[] = new ListRow(
                sprintf('%s-%04d%s', $directory ? 'katalog' : 'plik', $index, $directory ? '/' : '.txt'),
                $directory ? '' : sprintf('%d,%d kB', 1 + $index % 900, $index % 10),
                $directory ? Role::Accent : Role::Text,
            );
        }

        // Scenariusz `selection` łamie proporcję „jedno zaznaczenie na klatkę”
        // celowo: mierzy koszt samego paska, a nie typowej klatki.
        if ($everyRowSelected) {
            $primitives = [];

            foreach ($rows as $index => $row) {
                foreach ((new ListView([$row], 0))->draw($bounds->line($index)) as $primitive) {
                    $primitives[] = $primitive;
                }
            }

            return $primitives;
        }

        return (new ListView($rows, $selected, $scroll))->draw($bounds);
    }

    /** Lista dłuższa niż okno — inaczej enkoder w ogóle nie narysuje suwaka. */
    private function scroll(int $visibleRows): ScrollPosition
    {
        $visible = max(1, $visibleRows);
        $total = $visible * 5;

        return new ScrollPosition($visible * 2, $visible, $total);
    }

    private function modal(int $rows, int $columns): Plane
    {
        $lines = [];

        for ($index = 0; $index < self::POPUP_LINES; ++$index) {
            $lines[] = sprintf('Wiersz okienka numer %d z krótkim opisem.', $index + 1);
        }

        $dialog = new Dialog('plik-0007.txt', $lines);
        $size = $dialog->size();
        $bounds = new Rect(
            max(0, intdiv($rows - $size->rows, 2)),
            max(0, intdiv($columns - $size->columns, 2)),
            min($size->rows, $rows),
            min($size->columns, $columns),
        );

        return new Plane('modal', $bounds, $dialog->draw($bounds), opaque: true);
    }

    /**
     * Okno komend stoi nad paskiem stanu i jest tak wysokie, jak długa jest lista
     * podpowiedzi. Mierzymy je w kształcie, w jakim najczęściej się je widzi:
     * wpisany przedrostek i kilka pasujących nazw.
     */
    private function commandWindow(HudLayout $layout, int $rows, int $columns): Plane
    {
        $height = self::COMMAND_SUGGESTIONS + 3;
        $bottom = max(0, $layout->status->row - 1);
        $bounds = new Rect(max(0, $bottom - $height + 1), 0, min($height, $rows), $columns);
        $inner = Panel::inner($bounds);
        $primitives = (new Panel('COMMANDS'))->draw($bounds);
        $suggestions = [];

        for ($index = 0; $index < self::COMMAND_SUGGESTIONS; ++$index) {
            $suggestions[] = new ListRow(
                sprintf('core.komenda-%d', $index + 1),
                sprintf('opis komendy numer %d', $index + 1),
            );
        }

        foreach ((new ListView($suggestions, 0))->draw($inner->rowsFrom(0, $inner->rows - 1)) as $primitive) {
            $primitives[] = $primitive;
        }

        $input = new TextInput();
        $input->useValue('core.kom');
        $input->useTime(0.0);

        foreach ($input->draw($inner->line($inner->rows - 1)) as $primitive) {
            $primitives[] = $primitive;
        }

        return new Plane('command', $bounds, $primitives, opaque: true);
    }
}
