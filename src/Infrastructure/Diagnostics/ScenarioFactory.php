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
use LightManager\Presentation\Ui\Component\StatusBar;
use LightManager\Presentation\Ui\Component\TextInput;
use LightManager\Presentation\Ui\HudLayout;

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
        $layout = new HudLayout($rows, $columns, $scenario === Scenario::Thumbnail);
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
            default => $this->fullContent($layout, $list, $scenario),
        };
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
