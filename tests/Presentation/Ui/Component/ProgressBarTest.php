<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Primitive\Weight;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\Component\ProgressBar;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Pasek postępu — komponent, który powstaje **przed** swoim prawdziwym
 * użytkownikiem (`du` i `sha256` z kroku 25), więc ten plik jest jedyną osłoną
 * przed zaprojektowaniem go na domysł.
 *
 * Stąd jego kształt: nie sprawdza „czy rysuje”, tylko przechodzi po kolei
 * wszystko, czego krok 25 od paska zażąda — oba tryby, zero i sto procent,
 * wartość spoza zakresu, napis szerszy od paska, pusty prostokąt.
 */
final class ProgressBarTest extends TestCase
{
    public function testEmptyRectangleProducesNothing(): void
    {
        self::assertSame([], (new ProgressBar(0.5, 'cokolwiek'))->draw(new Rect(0, 0, 0, 0)));
        self::assertSame([], (new ProgressBar(null, 'cokolwiek'))->draw(new Rect(2, 3, 1, 0)));
    }

    public function testTrackCoversTheWholeRectangleEvenWithoutProgress(): void
    {
        $bars = self::barsOf((new ProgressBar(0.0))->draw(new Rect(1, 4, 2, 20)));

        self::assertCount(1, $bars, 'zero procent to sam tor, bez wypełnienia');
        self::assertSame(Role::Surface, $bars[0]->role);
        self::assertSame(Weight::Fill, $bars[0]->weight);
        self::assertTrue($bars[0]->bounds->equals(new Rect(1, 4, 2, 20)));
    }

    public function testFullProgressFillsTheTrackCompletely(): void
    {
        $bars = self::barsOf((new ProgressBar(1.0))->draw(new Rect(0, 0, 1, 20)));

        self::assertCount(2, $bars);
        self::assertSame(Role::Accent, $bars[1]->role);
        self::assertTrue($bars[1]->bounds->equals(new Rect(0, 0, 1, 20)));
    }

    public function testFillGrowsFromTheLeftEdgeInProportionToProgress(): void
    {
        $bars = self::barsOf((new ProgressBar(0.25))->draw(new Rect(3, 6, 1, 40)));

        self::assertCount(2, $bars);
        self::assertTrue($bars[1]->bounds->equals(new Rect(3, 6, 1, 10)));
    }

    /** Wypełnienie zajmuje pełną wysokość paska, bo pasek wypełnia to, co dostał. */
    public function testFillSpansEveryRowOfTheRectangle(): void
    {
        $bars = self::barsOf((new ProgressBar(0.5))->draw(new Rect(2, 0, 3, 20)));

        self::assertSame([2, 3], [$bars[1]->bounds->row, $bars[1]->bounds->rows]);
    }

    public function testProgressOutsideTheRangeIsClampedInsteadOfDrawnOutsideTheTrack(): void
    {
        $tooMuch = (new ProgressBar(4.2))->draw(new Rect(0, 0, 1, 20));
        $negative = (new ProgressBar(-3.0))->draw(new Rect(0, 0, 1, 20));

        self::assertTrue(self::barsOf($tooMuch)[1]->bounds->equals(new Rect(0, 0, 1, 20)));
        self::assertCount(1, self::barsOf($negative), 'wartość ujemna to zero procent, a nie pasek w lewo');
        self::assertSame('100%', self::textOf($tooMuch), 'liczba idzie z wartości przyciętej, nie surowej');
        self::assertSame('0%', self::textOf($negative));
    }

    /** Wartość, która liczbą nie jest, znaczy to samo, co jej brak. */
    public function testNotANumberFallsBackToTheUnknownMode(): void
    {
        $primitives = (new ProgressBar(NAN, 'praca', now: 0.0))->draw(new Rect(0, 0, 1, 20));

        self::assertStringNotContainsString('%', self::textOf($primitives));
    }

    public function testKnownProgressAppendsThePercentageToTheText(): void
    {
        $primitives = (new ProgressBar(0.55, 'sha256'))->draw(new Rect(0, 0, 1, 30));

        self::assertSame('sha256 55%', self::textOf($primitives));
    }

    public function testPercentageGoesThroughTheCatalogWhenTheTranslatorIsGiven(): void
    {
        $primitives = (new ProgressBar(0.07, 'sha256', translator: new StubTranslator()))
            ->draw(new Rect(0, 0, 1, 30));

        self::assertSame('sha256 format.percent(value=7)', self::textOf($primitives));
    }

    public function testProgressBarWithoutTextShowsThePercentageAlone(): void
    {
        self::assertSame('30%', self::textOf((new ProgressBar(0.3))->draw(new Rect(0, 0, 1, 20))));
    }

    /**
     * Najważniejsza asercja tego pliku i jedno z kryteriów ukończenia kroku:
     * tryb nieznany **nie udaje** trybu z liczbą.
     */
    public function testUnknownProgressNeverShowsANumber(): void
    {
        $primitives = (new ProgressBar(null, 'licze rozmiar', now: 0.4))->draw(new Rect(0, 0, 1, 30));

        self::assertSame('licze rozmiar', self::textOf($primitives));
    }

    public function testUnknownProgressDrawsASegmentNarrowerThanTheTrack(): void
    {
        $bars = self::barsOf((new ProgressBar(null, now: 0.0))->draw(new Rect(0, 0, 1, 40)));

        self::assertCount(2, $bars);
        self::assertSame(10, $bars[1]->bounds->columns, 'odcinek zajmuje czwartą część toru');
        self::assertSame(0, $bars[1]->bounds->column, 'w chwili zerowej stoi przy lewej krawędzi');
    }

    /**
     * Odcinek **zawraca**, a nie przeskakuje: w połowie cyklu stoi przy prawej
     * krawędzi, a w drugiej połowie wraca tą samą drogą.
     */
    public function testUnknownProgressTravelsThereAndBack(): void
    {
        $columnAt = static fn (float $now): int => self::barsOf(
            (new ProgressBar(null, now: $now))->draw(new Rect(0, 0, 1, 40)),
        )[1]->bounds->column;

        self::assertSame(15, $columnAt(0.6));
        self::assertSame(30, $columnAt(1.2), 'po jednym przejściu odcinek dotyka prawej krawędzi');
        self::assertSame(15, $columnAt(1.8));
        self::assertSame(0, $columnAt(2.4), 'i wraca na start — cykl się zamyka');
    }

    /** Tor na jedną kolumnę nie ma dokąd wędrować — wtedy wypełnia się cały. */
    public function testUnknownProgressFillsATrackTooNarrowForTheSegmentToTravel(): void
    {
        $bars = self::barsOf((new ProgressBar(null, now: 5.0))->draw(new Rect(0, 0, 1, 1)));

        self::assertTrue($bars[1]->bounds->equals(new Rect(0, 0, 1, 1)));
    }

    public function testTextWiderThanTheBarIsCutWithAnEllipsis(): void
    {
        $primitives = (new ProgressBar(null, 'licze rozmiar katalogu domowego'))->draw(new Rect(0, 0, 1, 12));

        self::assertSame('licze rozmi…', self::textOf($primitives));
        self::assertSame(12, mb_strlen(self::textOf($primitives)));
    }

    public function testTextIsCentredInTheMiddleRowOfTheRectangle(): void
    {
        $runs = self::runsOf((new ProgressBar(null, 'praca', now: 0.0))->draw(new Rect(4, 10, 3, 21)));

        self::assertCount(1, $runs);
        self::assertSame(5, $runs[0]->row, 'napis stoi w środkowym wierszu');
        self::assertSame(18, $runs[0]->column, '(21 - 5) / 2 = 8 kolumn od lewej krawędzi');
    }

    /**
     * Napis przechodzący przez krawędź wypełnienia rozpada się na dwa odcinki
     * o różnych rolach — inaczej litery w akcencie na akcencie zniknęłyby
     * w połowie słowa.
     */
    public function testTextCrossingTheFillEdgeChangesRoleExactlyThere(): void
    {
        // Tor 20 kolumn, wypełnienie 10; napis „50%” zaczyna się w kolumnie 8,
        // więc dwa pierwsze znaki leżą na wypełnieniu, a trzeci już nie.
        $runs = self::runsOf((new ProgressBar(0.5))->draw(new Rect(0, 0, 1, 20)));

        self::assertCount(2, $runs);
        self::assertSame([8, '50', Role::Background], [$runs[0]->column, $runs[0]->text, $runs[0]->role]);
        self::assertSame([10, '%', Role::Text], [$runs[1]->column, $runs[1]->text, $runs[1]->role]);
    }

    /** Napis leżący w całości poza wypełnieniem zostaje jednym odcinkiem. */
    public function testTextOutsideTheFillStaysASingleRun(): void
    {
        $runs = self::runsOf((new ProgressBar(0.0, 'czekam'))->draw(new Rect(0, 0, 1, 30)));

        self::assertCount(1, $runs);
        self::assertSame(Role::Text, $runs[0]->role);
    }

    /** Napis leżący w całości na wypełnieniu też — cięcie nie tworzy pustych odcinków. */
    public function testTextFullyOnTheFillStaysASingleRun(): void
    {
        $runs = self::runsOf((new ProgressBar(1.0, 'gotowe'))->draw(new Rect(0, 0, 1, 30)));

        self::assertCount(1, $runs);
        self::assertSame(Role::Background, $runs[0]->role);
    }

    /** Odcinek wędrujący tnie napis z **obu** stron, więc części bywają trzy. */
    public function testTravellingSegmentCanSplitTheTextIntoThreeRuns(): void
    {
        $runs = self::runsOf((new ProgressBar(null, 'licze rozmiar katalogu', now: 0.6))
            ->draw(new Rect(0, 0, 1, 40)));

        self::assertCount(3, $runs);
        self::assertSame([Role::Text, Role::Background, Role::Text], array_map(
            static fn (TextRun $run): Role => $run->role,
            $runs,
        ));
        self::assertSame('licze rozmiar katalogu', implode('', array_map(
            static fn (TextRun $run): string => $run->text,
            $runs,
        )));
    }

    public function testBarWithoutTextDrawsNoTextAtAll(): void
    {
        self::assertSame([], self::runsOf((new ProgressBar(null, now: 1.0))->draw(new Rect(0, 0, 1, 20))));
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<Bar>
     */
    private static function barsOf(array $primitives): array
    {
        return array_values(array_filter(
            $primitives,
            static fn (Primitive $primitive): bool => $primitive instanceof Bar,
        ));
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<TextRun>
     */
    private static function runsOf(array $primitives): array
    {
        return array_values(array_filter(
            $primitives,
            static fn (Primitive $primitive): bool => $primitive instanceof TextRun,
        ));
    }

    /** @param list<Primitive> $primitives */
    private static function textOf(array $primitives): string
    {
        return implode('', array_map(
            static fn (TextRun $run): string => $run->text,
            self::runsOf($primitives),
        ));
    }
}
