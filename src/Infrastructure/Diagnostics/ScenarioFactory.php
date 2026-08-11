<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Ui\Frame;
use LightManager\Application\Ui\Plane;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\Component\ImageBox;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\ProgressBar;
use LightManager\Presentation\Ui\Component\Section;
use LightManager\Presentation\Ui\Component\SectionList;
use LightManager\Presentation\Ui\Component\Split;
use LightManager\Presentation\Ui\Component\StatusBar;
use LightManager\Presentation\Ui\Component\TextInput;
use LightManager\Presentation\Ui\HudLayout;
use LightManager\Presentation\Ui\SplitAxis;

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

    private const HINTS = 'F1 pomoc · F2 ustawienia · F10 wyjście';

    private const POPUP_LINES = 6;

    /** Tyle podpowiedzi widać w oknie komend przy typowym wpisaniu przedrostka. */
    private const COMMAND_SUGGESTIONS = 5;

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
        $layout = new HudLayout($rows, $columns, withPreview: $scenario === Scenario::Thumbnail);
        $window = new Rect(0, 0, $rows, $columns);

        $planes = [new Plane('chrome', $window, $this->chrome($scenario, $layout))];
        $planes[] = new Plane('content', $window, $this->content($scenario, $layout));

        if ($scenario === Scenario::Popup) {
            $planes[] = $this->modal($rows, $columns);
        }

        if ($scenario === Scenario::Command) {
            $planes[] = $this->commandWindow($layout, $rows, $columns);
        }

        return new ScenarioFrame(new Frame($planes), $rows, $columns);
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
            [$layout->preview, $layout->previewIsPanel(), 'PREVIEW'],
            [$layout->status, $layout->statusIsPanel(), ''],
        ];

        foreach ($panels as [$zone, $isPanel, $label]) {
            // Klatka podzielona ma w miejscu strefy środkowej **dwie** obwódki
            // zamiast jednej — i tak samo jak jedna, leżą one w płaszczyźnie
            // spodniej, bo między klatkami się nie zmieniają.
            if ($scenario === Scenario::Split && $label === 'FILES') {
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
            Scenario::Split => $this->splitLists($layout),
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
     * komunikat i podpowiedzi, a przy scenariuszu z miniaturą — pas podglądu.
     *
     * @return list<Primitive>
     */
    private function fullContent(HudLayout $layout, Rect $list, Scenario $scenario): array
    {
        $primitives = (new Label(self::SAMPLE_PATH . '  —  12/240'))
            ->draw(HudLayout::contentOf($layout->header, $layout->headerIsPanel()));

        foreach ($this->list($list, selected: 2, scroll: $this->scroll($list->rows)) as $primitive) {
            $primitives[] = $primitive;
        }

        if ($scenario === Scenario::Thumbnail && !$layout->preview->isEmpty()) {
            $preview = HudLayout::contentOf($layout->preview, $layout->previewIsPanel());
            $box = new ImageBox($this->imagePath, '1600 × 1200 · JPEG · 412,3 kB');

            foreach ($box->draw($preview) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        $status = new StatusBar('· Pomiar wydajności potoku renderowania.', Role::Info, self::HINTS);

        foreach ($status->draw(HudLayout::contentOf($layout->status, $layout->statusIsPanel())) as $primitive) {
            $primitives[] = $primitive;
        }

        return $primitives;
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
