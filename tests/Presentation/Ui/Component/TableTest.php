<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Primitive\TextMark;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Presentation\Ui\Component\Align;
use LightManager\Presentation\Ui\Component\Column;
use LightManager\Presentation\Ui\Component\Table;
use LightManager\Presentation\Ui\Component\TableRow;
use LightManager\Presentation\Ui\Component\TextSpan;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * **Najważniejsza asercja kroku 30**: wiersz bez dopasowania oddaje co do
     * prymitywu to, co przed tym krokiem.
     *
     * Kryterium ukończenia tamtego kroku brzmi „klatka listy bez filtra nie ma
     * prawa zdrożeć ani o milisekundę”, a pomiar odpowiada na nie liczbą. To jest
     * odpowiedź strukturalna i jest od tamtej wcześniejsza: skoro nie ma
     * dodatkowego prymitywu, nie ma czego mierzyć.
     */
    public function testARowWithoutMatchesDrawsExactlyWhatItDrewBefore(): void
    {
        $plain = $this->table([new TableRow(['plik.txt', '1,2 kB', '2026-08-11 18:45', 'rw-r--r--'])]);
        $withEmptyMarks = $this->table([new TableRow(['plik.txt', '1,2 kB', '2026-08-11 18:45', 'rw-r--r--'], Role::Text, [])]);

        self::assertSame(
            self::signaturesOf($plain->draw(new Rect(0, 0, 1, 60))),
            self::signaturesOf($withEmptyMarks->draw(new Rect(0, 0, 1, 60))),
        );
        self::assertSame([], self::marksOf($plain->draw(new Rect(0, 0, 1, 60))));
    }

    /** @return array<string, array{int, int, string, int}> zakres → oczekiwana kolumna, długość i treść */
    public static function matchPositions(): array
    {
        return [
            'na początku nazwy' => [0, 4, 'plik', 0],
            'w środku nazwy' => [2, 2, 'ik', 2],
            'na końcu nazwy' => [5, 3, 'txt', 5],
        ];
    }

    #[DataProvider('matchPositions')]
    public function testHighlightSitsWhereTheMatchIs(int $offset, int $length, string $text, int $column): void
    {
        $marks = self::marksOf($this->marked([$offset => new TextSpan($offset, $length)])->draw(new Rect(0, 0, 1, 60)));

        self::assertCount(1, $marks);
        self::assertSame($column, $marks[0]->column);
        self::assertSame($text, $marks[0]->text);
        self::assertSame(0, $marks[0]->row);
    }

    /** Kilka dopasowań w jednym wierszu to kilka prymitywów, każdy w swoim miejscu. */
    public function testEveryMatchInARowGetsItsOwnHighlight(): void
    {
        $marks = self::marksOf(
            $this->marked([new TextSpan(0, 1), new TextSpan(3, 1)])->draw(new Rect(0, 0, 1, 60)),
        );

        self::assertSame([0, 3], array_map(static fn (TextMark $mark): int => $mark->column, $marks));
        self::assertSame(['p', 'k'], array_map(static fn (TextMark $mark): string => $mark->text, $marks));
    }

    /**
     * Dopasowanie przycięte razem z nazwą **nie sięga za wielokropek**.
     *
     * Wielokropek nie jest częścią dopasowania i pomalowanie go tłem sugerowałoby,
     * że pasująca treść ciągnie się dalej, niż widać.
     */
    public function testAMatchBeyondTheTrimmedNameIsDropped(): void
    {
        $long = str_repeat('a', 80);
        $primitives = (new Table(
            $this->columns(),
            [new TableRow([$long, '', '', ''], Role::Text, [0 => [new TextSpan(1, 2), new TextSpan(40, 3)]])],
        ))->draw(new Rect(0, 0, 1, 60));

        $marks = self::marksOf($primitives);

        self::assertCount(1, $marks, 'dopasowanie spoza widocznej części nazwy znika w całości');
        self::assertSame(1, $marks[0]->column);
        self::assertStringEndsWith('…', self::runsOf($primitives)[0]->text);
    }

    /** Dopasowanie sięgające **poza** przycięcie zostaje skrócone do tego, co widać. */
    public function testAMatchCrossingTheTrimIsShortened(): void
    {
        $long = str_repeat('a', 80);
        $marks = self::marksOf((new Table(
            $this->columns(),
            [new TableRow([$long, '', '', ''], Role::Text, [0 => [new TextSpan(21, 6)]])],
        ))->draw(new Rect(0, 0, 1, 60)));

        // Nazwa dostaje 24 kolumny, z których ostatnia jest wielokropkiem —
        // widocznej treści zostaje 23 znaki, więc z sześciu zostają dwa.
        self::assertCount(1, $marks);
        self::assertSame(2, mb_strlen($marks[0]->text));
    }

    /** Podświetlenie w kolumnie dosuniętej do prawej idzie za treścią, nie za brzegiem kolumny. */
    public function testHighlightFollowsARightAlignedCell(): void
    {
        $primitives = (new Table(
            $this->columns(),
            [new TableRow(['plik.txt', '1,2 kB', '', ''], Role::Text, [1 => [new TextSpan(0, 1)]])],
        ))->draw(new Rect(0, 0, 1, 60));

        $size = self::runsOf($primitives)[1];
        $marks = self::marksOf($primitives);

        self::assertCount(1, $marks);
        self::assertSame($size->column, $marks[0]->column);
        self::assertSame('1', $marks[0]->text);
    }

    /**
     * Dopasowanie w zaznaczonym wierszu **widać** — bo tło podświetlenia jest
     * akcentem, a nie rolą zaznaczenia.
     *
     * Gdyby było `Selection`, zniknęłoby dokładnie w tym wierszu, na który
     * użytkownik patrzy.
     */
    public function testHighlightStaysVisibleOnTheSelectedRow(): void
    {
        $marks = self::marksOf((new Table(
            $this->columns(),
            [new TableRow(['plik.txt', '', '', ''], Role::Text, [0 => [new TextSpan(0, 4)]])],
            0,
        ))->draw(new Rect(0, 0, 1, 60)));

        self::assertCount(1, $marks);
        self::assertSame(Role::Accent, $marks[0]->ground);
        self::assertNotSame(Role::Selection, $marks[0]->ground);
    }

    /** Nagłówek kolumn nie ma dopasowań i mieć ich nie może — nie jest treścią. */
    public function testTheHeaderRowIsNeverHighlighted(): void
    {
        $primitives = (new Table(
            $this->columns(),
            [new TableRow(['plik.txt', '', '', ''], Role::Text, [0 => [new TextSpan(0, 4)]])],
            null,
            null,
            true,
        ))->draw(new Rect(0, 0, 2, 60));

        $marks = self::marksOf($primitives);

        self::assertCount(1, $marks);
        self::assertSame(1, $marks[0]->row, 'podświetlenie leży w wierszu treści, nie w nagłówku');
    }

    /**
     * Wiersz z podświetleniem — dopasowania podane wprost, bo szukanie należy do
     * modułu, a nie do tabeli.
     *
     * @param list<TextSpan>|array<int, TextSpan> $spans
     */
    private function marked(array $spans): Table
    {
        return new Table(
            $this->columns(),
            [new TableRow(['plik.txt', '', '', ''], Role::Text, [0 => array_values($spans)])],
        );
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<TextMark>
     */
    private static function marksOf(array $primitives): array
    {
        return array_values(array_filter(
            $primitives,
            static fn (Primitive $primitive): bool => $primitive instanceof TextMark,
        ));
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<string>
     */
    private static function signaturesOf(array $primitives): array
    {
        return array_map(static fn (Primitive $primitive): string => $primitive->signature(), $primitives);
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
