<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\Spacer;
use LightManager\Presentation\Ui\Container\Slot;
use LightManager\Presentation\Ui\Container\VStack;

/**
 * Podział okna na trzy strefy układu „HUD”: ścieżkę, listę i pasek stanu.
 *
 * Sam podział robi `VStack` — tu mieszka **polityka**, czyli odpowiedź na
 * pytanie, ile która strefa chce dostać przy danej wysokości okna. Progi nie
 * wynikają z arytmetyki: mieszczą się i przy niższym oknie, a mimo to ustępują,
 * bo zabierałyby liście więcej, niż dają. Takiej reguły żaden kontener nie
 * zgadnie sam z siebie i dlatego nie próbuje.
 *
 * **Stref było cztery do kroku 47** — pas podglądu wyszedł z kontraktu ekranu
 * wraz z `preview()` (D76, D78), bo po wyprowadzeniu miniatury do modułu
 * `FileInfo` nie zamawiał go ani jeden ekran.
 *
 * Kolejność ustępowania jest w szczelinach — ścieżka oddaje wiersze przed
 * paskiem stanu, lista ostatnia — i przy dzisiejszych progach nie uruchamia się
 * nigdy poza oknem niższym niż trzy wiersze. Zostaje jako siatka bezpieczeństwa
 * na progi, których nikt nie przewidział.
 *
 * Od kroku 21 strefa górna jest **zamawiana przez ekran**: `$withHeader` mówi,
 * czy ekran w ogóle wystawił `ScreenZone`. Strefa niezamówiona nie dostaje ani
 * jednego wiersza, a jej miejsce zabiera szczelina elastyczna, czyli lista.
 *
 * Krok 40 dokłada pytanie, którego ten podział wcześniej nie znał: `$wideStatus`
 * mówi, czy podpowiedzi mieszczą się w jednym wierszu. Jest to **pierwsza
 * odpowiedź zależna od treści, a nie od rozmiaru okna**, i dlatego przychodzi
 * z zewnątrz gotowa — układ nie ma prawa czytać wiązań klawiszy, a `FrameComposer`
 * i tak musi je złożyć wcześniej, bo pasek stanu jest jego robotą.
 */
final class HudLayout
{
    /**
     * Poniżej tylu wierszy pasek stanu nie rośnie do dwóch wierszy, choćby
     * podpowiedzi się nie mieściły.
     *
     * Treść progu jest ta sama, co przed krokiem 47, tylko liczona bez
     * składnika, którego już nie ma: wiersz dokładany stopce zabiera się liście
     * (jedyna szczelina elastyczna), więc rośnie ona dopiero wtedy, gdy liście
     * zostaje z czego oddać. Do kroku 47 próg brzmiał `ROWS_FOR_PREVIEW + 2`
     * (czyli 28), bo dokładnie tam lista oddawała osiem wierszy pasowi podglądu.
     * Pas zniknął (D76, D78), więc lista ma przy **dwudziestu** wierszach tyle
     * samo, co miała przy dwudziestu ośmiu z pasem — i tam stoi ten próg.
     * W niższym oknie podpowiedzi ustępują pozycjami, nie wierszem listy.
     */
    private const ROWS_FOR_STATUS_LINES = 20;

    private const ROWS_FOR_HEADER_PANEL = 18;

    private const ROWS_FOR_STATUS_PANEL = 12;

    private const ROWS_FOR_LIST_PANEL = 8;

    /** Poniżej tego progu ścieżka i pasek stanu schodzą do jednego wiersza. */
    private const ROWS_FOR_HEADER_LINE = 3;

    private const ROWS_FOR_STATUS_LINE = 2;

    public readonly Rect $header;

    public readonly Rect $list;

    public readonly Rect $status;

    private readonly int $rows;

    private readonly bool $wideStatus;

    /**
     * @param bool $wideStatus czy podpowiedzi nie zmieściły się w jednym wierszu
     *                         — pytanie zadaje `FrameComposer`, bo tylko on zna
     *                         ich treść
     */
    public function __construct(
        int $rows,
        int $columns,
        bool $withHeader = true,
        bool $wideStatus = false,
    ) {
        $this->rows = max(1, $rows);
        $this->wideStatus = $wideStatus;
        $columns = max(1, $columns);

        $spacer = new Spacer();
        $heights = (new VStack([
            Slot::fixed($spacer, $withHeader ? $this->headerRows() : 0, 2),
            Slot::flexible($spacer),
            Slot::fixed($spacer, $this->statusRows(), 3),
        ]))->distribute($this->rows);

        $top = 0;
        $zones = [];

        foreach ($heights as $height) {
            $zones[] = new Rect($top, 0, $height, $columns);
            $top += $height;
        }

        [$this->header, $this->list, $this->status] = $zones;
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
            self::contentColumns($zone->columns),
        );
    }

    /**
     * Szerokość treści strefy — **ta sama w obu wariantach oprawy**: panel zjada
     * po dwie kolumny obwódką, goły wiersz tyle samo oddechem.
     *
     * Fakt wygląda na drobiazg, a przesądza o tym, że rachunek stopki nie kręci
     * się w kółko (krok 40): `FrameComposer` musi znać szerokość podpowiedzi,
     * zanim `HudLayout` powstanie, bo od tego zależy wysokość strefy — a wysokość
     * na szerokość nie wpływa.
     */
    public static function contentColumns(int $columns): int
    {
        return max(0, $columns - 2 * Panel::CONTENT_COLUMN);
    }

    private function headerRows(): int
    {
        return match (true) {
            $this->rows >= self::ROWS_FOR_HEADER_PANEL => 3,
            $this->rows >= self::ROWS_FOR_HEADER_LINE => 1,
            default => 0,
        };
    }

    /**
     * Wysokość paska stanu. Wariant czterowierszowy to **panel o dwóch wierszach
     * treści**, bo obwódka bierze dwa — trzy wiersze zostają przy jednym wierszu
     * podpowiedzi, dokładnie jak przed krokiem 40.
     */
    private function statusRows(): int
    {
        return match (true) {
            $this->wideStatus && $this->rows >= self::ROWS_FOR_STATUS_LINES => 4,
            $this->rows >= self::ROWS_FOR_STATUS_PANEL => 3,
            $this->rows >= self::ROWS_FOR_STATUS_LINE => 1,
            default => 0,
        };
    }

}
