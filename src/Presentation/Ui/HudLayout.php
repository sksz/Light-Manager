<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\Spacer;
use LightManager\Presentation\Ui\Container\Slot;
use LightManager\Presentation\Ui\Container\VStack;

/**
 * Podział okna na cztery strefy układu „HUD”: ścieżkę, listę, pas podglądu
 * i pasek stanu.
 *
 * Sam podział robi `VStack` — tu mieszka **polityka**, czyli odpowiedź na
 * pytanie, ile która strefa chce dostać przy danej wysokości okna. Progi są te
 * same, co w usuniętym `HudFrameLayoutService`, i nie wynikają z arytmetyki:
 * przy dwudziestu wierszach pas podglądu **mieści się**, a mimo to go nie ma, bo
 * zabierałby liście więcej, niż daje. Takiej reguły żaden kontener nie zgadnie
 * sam z siebie i dlatego nie próbuje.
 *
 * Kolejność ustępowania jest w szczelinach — pas podglądu oddaje wiersze
 * pierwszy, lista ostatnia — i przy dzisiejszych progach nie uruchamia się
 * nigdy poza oknem niższym niż trzy wiersze. Zostaje jako siatka bezpieczeństwa
 * na progi, których nikt nie przewidział.
 *
 * Od kroku 21 obie strefy skrajne są **zamawiane przez ekran**: `$withHeader`
 * i `$withPreview` mówią, czy ekran w ogóle wystawił `ScreenZone`. Progi zostają
 * nietknięte — zmienia się wyłącznie źródło odpowiedzi „czy strefa ma powstać”.
 * Strefa niezamówiona nie dostaje ani jednego wiersza, a jej miejsce zabiera
 * szczelina elastyczna, czyli lista.
 */
final class HudLayout
{
    /** Poniżej tylu wierszy pas podglądu zabierałby liście więcej, niż daje. */
    private const ROWS_FOR_PREVIEW = 26;

    private const PREVIEW_INNER_ROWS = 6;

    private const ROWS_FOR_HEADER_PANEL = 18;

    private const ROWS_FOR_STATUS_PANEL = 12;

    private const ROWS_FOR_LIST_PANEL = 8;

    /** Poniżej tego progu ścieżka i pasek stanu schodzą do jednego wiersza. */
    private const ROWS_FOR_HEADER_LINE = 3;

    private const ROWS_FOR_STATUS_LINE = 2;

    public readonly Rect $header;

    public readonly Rect $list;

    public readonly Rect $preview;

    public readonly Rect $status;

    private readonly int $rows;

    public function __construct(int $rows, int $columns, bool $withHeader = true, bool $withPreview = false)
    {
        $this->rows = max(1, $rows);
        $columns = max(1, $columns);

        $spacer = new Spacer();
        $heights = (new VStack([
            Slot::fixed($spacer, $withHeader ? $this->headerRows() : 0, 2),
            Slot::flexible($spacer),
            Slot::fixed($spacer, $withPreview ? $this->previewRows() : 0, 0),
            Slot::fixed($spacer, $this->statusRows(), 3),
        ]))->distribute($this->rows);

        $top = 0;
        $zones = [];

        foreach ($heights as $height) {
            $zones[] = new Rect($top, 0, $height, $columns);
            $top += $height;
        }

        [$this->header, $this->list, $this->preview, $this->status] = $zones;
    }

    public function headerIsPanel(): bool
    {
        return $this->header->rows >= 3;
    }

    public function listIsPanel(): bool
    {
        return $this->rows >= self::ROWS_FOR_LIST_PANEL && $this->list->rows >= 3;
    }

    public function statusIsPanel(): bool
    {
        return $this->status->rows >= 3;
    }

    public function previewIsPanel(): bool
    {
        return $this->preview->rows >= 3;
    }

    /**
     * Prostokąt treści strefy: wewnątrz obwódki, gdy strefa jest panelem, albo
     * z samym oddechem po bokach, gdy zeszła do gołego wiersza. Oddech zostaje
     * w obu przypadkach — inaczej tekst przy krawędzi okna zmieniałby położenie
     * przy każdym przełączeniu progu.
     */
    public static function contentOf(Rect $zone, bool $panel): Rect
    {
        if ($panel) {
            return Panel::inner($zone);
        }

        return new Rect(
            $zone->row,
            $zone->column + Panel::CONTENT_COLUMN,
            $zone->rows,
            max(0, $zone->columns - 2 * Panel::CONTENT_COLUMN),
        );
    }

    private function headerRows(): int
    {
        return match (true) {
            $this->rows >= self::ROWS_FOR_HEADER_PANEL => 3,
            $this->rows >= self::ROWS_FOR_HEADER_LINE => 1,
            default => 0,
        };
    }

    private function statusRows(): int
    {
        return match (true) {
            $this->rows >= self::ROWS_FOR_STATUS_PANEL => 3,
            $this->rows >= self::ROWS_FOR_STATUS_LINE => 1,
            default => 0,
        };
    }

    private function previewRows(): int
    {
        return $this->rows >= self::ROWS_FOR_PREVIEW ? self::PREVIEW_INNER_ROWS + 2 : 0;
    }
}
