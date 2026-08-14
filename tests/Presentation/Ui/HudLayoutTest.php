<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui;

use LightManager\Presentation\Ui\HudLayout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test-wyrocznia drabinki ustępowania stref.
 *
 * Tabela poniżej to **podział, jaki dawał `HudFrameLayoutService`** — usługa
 * z kroku 13, którą krok 18 zastąpił kontenerem. Zgodność sprawdzono dla
 * wszystkich wysokości okna od 1 do 60 wierszy, zanim starą usługę usunięto;
 * dopiero wynik tego porównania został tu zamrożony. Tabela nie jest więc
 * kopią nowej implementacji, tylko śladem po poprzedniej — i to ona pilnuje,
 * żeby progi dopracowane pomiarami w kroku 13 nie rozjechały się po cichu.
 *
 * Progi nie wynikają z arytmetyki: przy dwudziestu wierszach pas podglądu
 * **mieści się**, a mimo to go nie ma, bo zabierałby liście więcej, niż daje.
 */
final class HudLayoutTest extends TestCase
{
    /** @return array<string, array{int, list<int>}> */
    public static function windowHeights(): array
    {
        $expected = [
            1 => [0, 1, 0, 0],
            2 => [0, 1, 0, 1],
            3 => [1, 1, 0, 1],
            4 => [1, 2, 0, 1],
            5 => [1, 3, 0, 1],
            6 => [1, 4, 0, 1],
            7 => [1, 5, 0, 1],
            8 => [1, 6, 0, 1],
            9 => [1, 7, 0, 1],
            10 => [1, 8, 0, 1],
            11 => [1, 9, 0, 1],
            12 => [1, 8, 0, 3],
            13 => [1, 9, 0, 3],
            14 => [1, 10, 0, 3],
            15 => [1, 11, 0, 3],
            16 => [1, 12, 0, 3],
            17 => [1, 13, 0, 3],
            18 => [3, 12, 0, 3],
            19 => [3, 13, 0, 3],
            20 => [3, 14, 0, 3],
            21 => [3, 15, 0, 3],
            22 => [3, 16, 0, 3],
            23 => [3, 17, 0, 3],
            24 => [3, 18, 0, 3],
            25 => [3, 19, 0, 3],
            26 => [3, 12, 8, 3],
            27 => [3, 13, 8, 3],
            28 => [3, 14, 8, 3],
            29 => [3, 15, 8, 3],
            30 => [3, 16, 8, 3],
            31 => [3, 17, 8, 3],
            32 => [3, 18, 8, 3],
            33 => [3, 19, 8, 3],
            34 => [3, 20, 8, 3],
            35 => [3, 21, 8, 3],
            36 => [3, 22, 8, 3],
            37 => [3, 23, 8, 3],
            38 => [3, 24, 8, 3],
            39 => [3, 25, 8, 3],
            40 => [3, 26, 8, 3],
            41 => [3, 27, 8, 3],
            42 => [3, 28, 8, 3],
            43 => [3, 29, 8, 3],
            44 => [3, 30, 8, 3],
            45 => [3, 31, 8, 3],
            46 => [3, 32, 8, 3],
            47 => [3, 33, 8, 3],
            48 => [3, 34, 8, 3],
            49 => [3, 35, 8, 3],
            50 => [3, 36, 8, 3],
            51 => [3, 37, 8, 3],
            52 => [3, 38, 8, 3],
            53 => [3, 39, 8, 3],
            54 => [3, 40, 8, 3],
            55 => [3, 41, 8, 3],
            56 => [3, 42, 8, 3],
            57 => [3, 43, 8, 3],
            58 => [3, 44, 8, 3],
            59 => [3, 45, 8, 3],
            60 => [3, 46, 8, 3],
        ];

        $cases = [];

        foreach ($expected as $rows => $zones) {
            $cases[$rows . ' wierszy'] = [$rows, $zones];
        }

        return $cases;
    }

    /** @param list<int> $expected wysokości stref: ścieżka, lista, podgląd, pasek stanu */
    #[DataProvider('windowHeights')]
    public function testMatchesTheLadderFromStepThirteen(int $rows, array $expected): void
    {
        $layout = new HudLayout($rows, 100, withPreview: true);

        self::assertSame($expected, [
            $layout->header->rows,
            $layout->list->rows,
            $layout->preview->rows,
            $layout->status->rows,
        ]);
    }

    /** @param list<int> $expected */
    #[DataProvider('windowHeights')]
    public function testZonesTileTheWindowWithoutGapsOrOverlap(int $rows, array $expected): void
    {
        $layout = new HudLayout($rows, 100, withPreview: true);
        $top = 0;

        foreach ([$layout->header, $layout->list, $layout->preview, $layout->status] as $zone) {
            self::assertSame($top, $zone->row);
            $top += $zone->rows;
        }

        self::assertSame($rows, $top);
    }

    public function testEcranWithoutPreviewGivesThoseRowsToTheList(): void
    {
        $withPreview = new HudLayout(40, 100, withPreview: true);
        $without = new HudLayout(40, 100, withPreview: false);

        self::assertSame(0, $without->preview->rows);
        self::assertSame($withPreview->list->rows + $withPreview->preview->rows, $without->list->rows);
    }

    /**
     * Od kroku 21 ekran zamawia także **górny pas**, a strefa niezamówiona nie
     * dostaje ani jednego wiersza — wszystkie idą do listy.
     */
    public function testScreenWithoutHeaderGivesThoseRowsToTheList(): void
    {
        $withHeader = new HudLayout(40, 100);
        $without = new HudLayout(40, 100, withHeader: false);

        self::assertSame(0, $without->header->rows);
        self::assertSame($withHeader->list->rows + $withHeader->header->rows, $without->list->rows);
    }

    public function testPanelThresholdsFollowTheZoneHeights(): void
    {
        $tall = new HudLayout(40, 100, withPreview: true);

        self::assertTrue($tall->headerIsPanel());
        self::assertTrue($tall->listIsPanel());
        self::assertTrue($tall->previewIsPanel());
        self::assertTrue($tall->statusIsPanel());

        $short = new HudLayout(6, 100, withPreview: true);

        self::assertFalse($short->headerIsPanel());
        self::assertFalse($short->listIsPanel());
        self::assertFalse($short->statusIsPanel());
    }

    /**
     * Pasek stanu rośnie do dwóch wierszy **z potrzeby i tylko wysoko** (krok 40,
     * rozstrzygnięcie nr 6). Wiersz zabiera się liście — jedynej szczelinie
     * elastycznej — więc pas podglądu zostaje nietknięty.
     */
    public function testTheStatusBarGrowsByARowTakenFromTheList(): void
    {
        $narrow = new HudLayout(40, 100, withPreview: true);
        $wide = new HudLayout(40, 100, withPreview: true, wideStatus: true);

        self::assertSame(3, $narrow->status->rows);
        self::assertSame(4, $wide->status->rows);
        self::assertSame($narrow->preview->rows, $wide->preview->rows, 'pas podglądu nie oddaje wiersza');
        self::assertSame($narrow->header->rows, $wide->header->rows);
        self::assertSame($narrow->list->rows - 1, $wide->list->rows, 'wiersz bierze się liście');
        self::assertTrue($wide->statusIsPanel());
    }

    public function testInALowWindowTheStatusBarDoesNotGrowAtAll(): void
    {
        foreach ([27, 20, 12, 6, 2] as $rows) {
            self::assertSame(
                (new HudLayout($rows, 100))->status->rows,
                (new HudLayout($rows, 100, wideStatus: true))->status->rows,
                'poniżej progu podpowiedzi ustępują pozycjami, a nie wierszem listy: ' . $rows,
            );
        }
    }
}
