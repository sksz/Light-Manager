<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Presentation\Ui\Component\Align;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use PHPUnit\Framework\TestCase;

/**
 * Tabela — krok 27.
 *
 * Rachunek szerokości pilnuje `DistributionTest`; ten zestaw patrzy na to, co
 * z niego wynika **w prymitywach**: gdzie zaczyna się napis, czy kolumny sąsiadów
 * się nie stykają, czy dosunięcie do prawej trafia w krawędź i czy kolumna, która
 * ustąpiła, naprawdę zniknęła z klatki zamiast narysować się w zerowej szerokości.
 */
final class TableTest extends TestCase
{
    public function testColumnsStartWhereTheDistributionPutsThem(): void
    {
        $runs = self::runsOf($this->table()->draw(new Rect(0, 0, 1, 60)));

        // Nazwa od lewej krawędzi, rozmiar dosunięty do prawej swojej kolumny,
        // data zaraz za nim, prawa na końcu.
        self::assertSame([0, 27, 34, 51], array_map(static fn (TextRun $run): int => $run->column, $runs));
        self::assertSame(['plik.txt', '1,2 kB', '2026-08-11 18:45', 'rw-r--r--'], array_map(
            static fn (TextRun $run): string => $run->text,
            $runs,
        ));
    }

    /** Żadne dwie sąsiednie komórki nie stykają się bokami. */
    public function testNeighbouringCellsNeverTouch(): void
    {
        $runs = self::runsOf($this->table([
            new TableRow([str_repeat('a', 80), '999,9 GB', '2026-08-11 18:45', 'rwxr-xr-x']),
        ])->draw(new Rect(0, 0, 1, 60)));

        for ($index = 1; $index < count($runs); ++$index) {
            $previous = $runs[$index - 1];
            $end = $previous->column + mb_strlen($previous->text);

            self::assertLessThan($runs[$index]->column, $end, 'komórki stykają się bokami');
        }
    }

    /**
     * Dosunięcie do prawej trafia w prawą krawędź **miejsca na treść**, a nie
     * w brzeg kolumny: ostatnia kolumna komórki jest odstępem od sąsiada.
     *
     * Kolumna rozmiaru zaczyna się na 25 i ma dziewięć kolumn, z których ostatnia
     * jest oddechem — treść kończy się więc na 32, a nie na 33.
     */
    public function testRightAlignedCellEndsAtTheEdgeOfItsContentRoom(): void
    {
        $runs = self::runsOf($this->table([new TableRow(['a', '7 B', '2026-08-11 18:45', 'rw-r--r--'])])
            ->draw(new Rect(0, 0, 1, 60)));

        self::assertSame(32, $runs[1]->column + mb_strlen($runs[1]->text) - 1);
    }

    /**
     * Kolumna, która ustąpiła, **nie rysuje się wcale** — a nie rysuje w zerowej
     * szerokości. Prymityw o pustej treści byłby kosztem bez obrazu.
     */
    public function testAColumnThatYieldedDrawsNothing(): void
    {
        $texts = array_map(
            static fn (TextRun $run): string => $run->text,
            self::runsOf($this->table()->draw(new Rect(0, 0, 1, 40))),
        );

        self::assertSame(['plik.txt', '1,2 kB'], $texts, 'prawa i data ustąpiły, nazwa i rozmiar zostały');
    }

    public function testNameSurvivesTheNarrowestPanel(): void
    {
        $texts = array_map(
            static fn (TextRun $run): string => $run->text,
            self::runsOf($this->table()->draw(new Rect(0, 0, 1, 12))),
        );

        self::assertSame(['plik.txt'], $texts);
    }

    /** Treść dłuższa od komórki kończy się wielokropkiem — regułą `Label::fit()`. */
    public function testTooLongContentIsTrimmedWithAnEllipsis(): void
    {
        $runs = self::runsOf($this->table([new TableRow([str_repeat('a', 80), '', '', ''])])
            ->draw(new Rect(0, 0, 1, 60)));

        self::assertSame(24, mb_strlen($runs[0]->text));
        self::assertStringEndsWith('…', $runs[0]->text);
    }

    public function testHeaderTakesTheFirstRowAndPushesContentDown(): void
    {
        $primitives = $this->table(header: true)->draw(new Rect(0, 0, 2, 60));
        $runs = self::runsOf($primitives);

        self::assertSame('Nazwa', $runs[0]->text);
        self::assertSame(0, $runs[0]->row);
        self::assertSame(Role::Muted, $runs[0]->role);
        self::assertSame('plik.txt', $runs[4]->text);
        self::assertSame(1, $runs[4]->row);
    }

    public function testCapacityCountsTheHeaderRow(): void
    {
        self::assertSame(10, Table::capacityOf(new Rect(0, 0, 10, 60), withHeader: false));
        self::assertSame(9, Table::capacityOf(new Rect(0, 0, 10, 60), withHeader: true));
        self::assertSame(0, Table::capacityOf(new Rect(0, 0, 0, 60), withHeader: true));
    }

    /** Rola zaznaczenia wygrywa z rolą kolumny — inaczej wyszarzony rozmiar znika. */
    public function testSelectionOverridesEveryColumnRole(): void
    {
        $primitives = $this->table()->draw(new Rect(0, 0, 1, 60), );
        $selected = (new Table($this->columns(), $this->rows(), 0))->draw(new Rect(0, 0, 1, 60));

        self::assertSame(Role::Muted, self::runsOf($primitives)[1]->role, 'bez zaznaczenia rozmiar jest stonowany');

        foreach (self::runsOf($selected) as $run) {
            self::assertSame(Role::SelectionText, $run->role);
        }

        self::assertInstanceOf(Bar::class, array_values(array_filter(
            $selected,
            static fn (Primitive $primitive): bool => $primitive instanceof Bar,
        ))[0], 'pasek pod kursorem pochodzi z `Highlight`');
    }

    /**
     * Suwak dostaje **własną kolumnę**, a nie kładzie się na treści: prawa
     * kolumna tabeli jest daną dosuniętą do brzegu, więc suwak przykryłby jej
     * ostatni znak.
     */
    public function testScrollbarTakesItsOwnColumnFromTheContent(): void
    {
        $withoutBar = $this->table()->draw(new Rect(0, 0, 1, 60));
        $withBar = (new Table($this->columns(), $this->rows(), null, new ScrollPosition(0, 5, 40)))
            ->draw(new Rect(0, 0, 1, 60));

        $lastWithout = self::runsOf($withoutBar)[3];
        $lastWith = self::runsOf($withBar)[3];

        self::assertSame($lastWithout->column - 1, $lastWith->column, 'treść cofnęła się o kolumnę suwaka');
        self::assertCount(1, array_filter($withBar, static fn (Primitive $p): bool => $p instanceof Scrollbar));
    }

    public function testEmptyBoundsDrawNothing(): void
    {
        self::assertSame([], $this->table()->draw(new Rect(0, 0, 0, 0)));
        self::assertSame([], (new Table([], $this->rows()))->draw(new Rect(0, 0, 1, 60)));
    }

    /** Wiersz o mniejszej liczbie komórek niż kolumn nie wywraca tabeli. */
    public function testAShortRowLeavesTheMissingCellsEmpty(): void
    {
        $runs = self::runsOf($this->table([new TableRow(['sam.txt'])])->draw(new Rect(0, 0, 1, 60)));

        self::assertCount(1, $runs);
        self::assertSame('sam.txt', $runs[0]->text);
    }

    /** @param list<TableRow> $rows */
    private function table(?array $rows = null, bool $header = false): Table
    {
        return new Table($this->columns(), $rows ?? $this->rows(), null, null, $header);
    }

    /** @return list<Column> */
    private function columns(): array
    {
        return [
            Column::flexible(20, label: 'Nazwa'),
            Column::fixed(9, yieldOrder: 3, align: Align::Right, label: 'Rozmiar', role: Role::Muted),
            Column::fixed(17, yieldOrder: 2, label: 'Zmieniony', role: Role::Muted),
            Column::fixed(9, yieldOrder: 1, label: 'Prawa', role: Role::Muted),
        ];
    }

    /** @return list<TableRow> */
    private function rows(): array
    {
        return [new TableRow(['plik.txt', '1,2 kB', '2026-08-11 18:45', 'rw-r--r--'])];
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<TextRun>
     */
    private static function runsOf(array $primitives): array
    {
        $runs = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $runs[] = $primitive;
            }
        }

        return $runs;
    }
}
