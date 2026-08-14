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
 * **Krok 47 zabrał tabeli kolumnę, a nie wiersze** (D78): pas podglądu wyszedł
 * z kontraktu ekranu, więc zostały trzy strefy. Drabinka ścieżki i paska stanu
 * jest przez to **nietknięta co do liczby** — zmieniła się wyłącznie lista, która
 * wchłonęła osiem wierszy zabieranych dotąd pasowi powyżej dwudziestego szóstego
 * wiersza okna. Porównanie ze starą tabelą jest więc dalej możliwe: wystarczy
 * dodać do listy to, co stało w kolumnie podglądu.
 */
final class HudLayoutTest extends TestCase
{
    /** @return array<string, array{int, list<int>}> */
    public static function windowHeights(): array
    {
        $expected = [
            1 => [0, 1, 0],
            2 => [0, 1, 1],
            3 => [1, 1, 1],
            4 => [1, 2, 1],
            5 => [1, 3, 1],
            6 => [1, 4, 1],
            7 => [1, 5, 1],
            8 => [1, 6, 1],
            9 => [1, 7, 1],
            10 => [1, 8, 1],
            11 => [1, 9, 1],
            12 => [1, 8, 3],
            13 => [1, 9, 3],
            14 => [1, 10, 3],
            15 => [1, 11, 3],
            16 => [1, 12, 3],
            17 => [1, 13, 3],
            18 => [3, 12, 3],
            19 => [3, 13, 3],
            20 => [3, 14, 3],
            21 => [3, 15, 3],
            22 => [3, 16, 3],
            23 => [3, 17, 3],
            24 => [3, 18, 3],
            25 => [3, 19, 3],
            26 => [3, 20, 3],
            27 => [3, 21, 3],
            28 => [3, 22, 3],
            29 => [3, 23, 3],
            30 => [3, 24, 3],
            31 => [3, 25, 3],
            32 => [3, 26, 3],
            33 => [3, 27, 3],
            34 => [3, 28, 3],
            35 => [3, 29, 3],
            36 => [3, 30, 3],
            37 => [3, 31, 3],
            38 => [3, 32, 3],
            39 => [3, 33, 3],
            40 => [3, 34, 3],
            41 => [3, 35, 3],
            42 => [3, 36, 3],
            43 => [3, 37, 3],
            44 => [3, 38, 3],
            45 => [3, 39, 3],
            46 => [3, 40, 3],
            47 => [3, 41, 3],
            48 => [3, 42, 3],
            49 => [3, 43, 3],
            50 => [3, 44, 3],
            51 => [3, 45, 3],
            52 => [3, 46, 3],
            53 => [3, 47, 3],
            54 => [3, 48, 3],
            55 => [3, 49, 3],
            56 => [3, 50, 3],
            57 => [3, 51, 3],
            58 => [3, 52, 3],
            59 => [3, 53, 3],
            60 => [3, 54, 3],
        ];

        $cases = [];

        foreach ($expected as $rows => $zones) {
            $cases[$rows . ' wierszy'] = [$rows, $zones];
        }

        return $cases;
    }

    /** @param list<int> $expected wysokości stref: ścieżka, lista, pasek stanu */
    #[DataProvider('windowHeights')]
    public function testMatchesTheLadderFromStepThirteen(int $rows, array $expected): void
    {
        $layout = new HudLayout($rows, 100);

        self::assertSame($expected, [
            $layout->header->rows,
            $layout->list->rows,
            $layout->status->rows,
        ]);
    }

    /** @param list<int> $expected */
    #[DataProvider('windowHeights')]
    public function testZonesTileTheWindowWithoutGapsOrOverlap(int $rows, array $expected): void
    {
        $layout = new HudLayout($rows, 100);
        $top = 0;

        foreach ([$layout->header, $layout->list, $layout->status] as $zone) {
            self::assertSame($top, $zone->row);
            $top += $zone->rows;
        }

        self::assertSame($rows, $top);
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
        $tall = new HudLayout(40, 100);

        self::assertTrue($tall->headerIsPanel());
        self::assertTrue($tall->listIsPanel());
        self::assertTrue($tall->statusIsPanel());

        $short = new HudLayout(6, 100);

        self::assertFalse($short->headerIsPanel());
        self::assertFalse($short->listIsPanel());
        self::assertFalse($short->statusIsPanel());
    }

    /**
     * Pasek stanu rośnie do dwóch wierszy **z potrzeby i tylko wysoko** (krok 40,
     * rozstrzygnięcie nr 6). Wiersz zabiera się liście — jedynej szczelinie
     * elastycznej.
     */
    public function testTheStatusBarGrowsByARowTakenFromTheList(): void
    {
        $narrow = new HudLayout(40, 100);
        $wide = new HudLayout(40, 100, wideStatus: true);

        self::assertSame(3, $narrow->status->rows);
        self::assertSame(4, $wide->status->rows);
        self::assertSame($narrow->header->rows, $wide->header->rows);
        self::assertSame($narrow->list->rows - 1, $wide->list->rows, 'wiersz bierze się liście');
        self::assertTrue($wide->statusIsPanel());
    }

    /**
     * **Próg dwuwierszowej stopki przesunął się o wysokość zniesionej strefy**
     * (krok 47, D78, rozstrzygnięcie 5): 28 → 20.
     *
     * Argument kroku 40 zostaje ten sam — pasek rośnie dopiero wtedy, gdy liście
     * zostaje z czego oddać — a zmieniła się wyłącznie arytmetyka: pas podglądu
     * brał osiem wierszy, więc lista ma przy dwudziestu tyle samo, ile miała przy
     * dwudziestu ośmiu z pasem.
     */
    public function testTheStatusBarThresholdMovedDownByTheHeightOfTheGoneStrip(): void
    {
        self::assertSame(4, (new HudLayout(20, 100, wideStatus: true))->status->rows);
        self::assertSame(3, (new HudLayout(19, 100, wideStatus: true))->status->rows);

        // Rachunek, na którym stoi całe rozstrzygnięcie: przy starym progu (28
        // wierszy okna, pas podglądu 8, stopka 4, ścieżka 3) liście zostawało
        // trzynaście wierszy. Przy nowym progu (20 wierszy okna, bez pasa) zostaje
        // ich dokładnie tyle samo — i to jest ten sam argument, nie nowa liczba.
        self::assertSame(13, (new HudLayout(20, 100, wideStatus: true))->list->rows);
    }

    public function testInALowWindowTheStatusBarDoesNotGrowAtAll(): void
    {
        foreach ([19, 14, 12, 6, 2] as $rows) {
            self::assertSame(
                (new HudLayout($rows, 100))->status->rows,
                (new HudLayout($rows, 100, wideStatus: true))->status->rows,
                'poniżej progu podpowiedzi ustępują pozycjami, a nie wierszem listy: ' . $rows,
            );
        }
    }
}
