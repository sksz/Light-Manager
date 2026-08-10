<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Diagnostics;

use LightManager\Infrastructure\Diagnostics\DiagnosticsException;
use LightManager\Infrastructure\Diagnostics\DiagnosticsProblem;
use LightManager\Infrastructure\Diagnostics\Measurement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Reguła „kiedy pomiarowi wolno wierzyć” jest tu sprawdzana testem, a nie
 * oglądana na wydruku — po to część obliczeniowa narzędzia została oddzielona
 * od Imagicka i terminala.
 */
final class MeasurementTest extends TestCase
{
    public function testMedianOfOddCountIsTheMiddleSample(): void
    {
        $measurement = Measurement::fromSamples([30.0, 10.0, 20.0]);

        self::assertSame(20.0, $measurement->median);
        self::assertSame(10.0, $measurement->minimum);
        self::assertSame(30.0, $measurement->maximum);
        self::assertSame(3, $measurement->count);
    }

    public function testMedianOfEvenCountAveragesTheTwoMiddleSamples(): void
    {
        self::assertSame(25.0, Measurement::fromSamples([10.0, 20.0, 30.0, 40.0])->median);
    }

    /** Kolejność próbek nie ma prawa wpływać na wynik — pomiar przychodzi nieposortowany. */
    public function testOrderOfSamplesDoesNotMatter(): void
    {
        $ordered = Measurement::fromSamples([1.0, 2.0, 3.0, 4.0, 5.0]);
        $shuffled = Measurement::fromSamples([4.0, 1.0, 5.0, 2.0, 3.0]);

        self::assertSame($ordered->median, $shuffled->median);
        self::assertSame($ordered->minimum, $shuffled->minimum);
        self::assertSame($ordered->maximum, $shuffled->maximum);
    }

    public function testSingleSampleIsNeverUnstable(): void
    {
        $measurement = Measurement::fromSamples([100.0]);

        self::assertFalse($measurement->isUnstable());
        self::assertSame(100.0, $measurement->median);
    }

    /** Rozrzut zaobserwowany w kroku 13 (184–254 ms) musi wpaść w ostrzeżenie. */
    public function testSpreadObservedInStepThirteenIsReportedAsUnstable(): void
    {
        $measurement = Measurement::fromSamples([184.0, 201.0, 218.0, 254.0]);

        self::assertTrue($measurement->isUnstable());
        self::assertEqualsWithDelta(1.38, $measurement->spreadRatio(), 0.01);
    }

    public function testTightSpreadIsStable(): void
    {
        self::assertFalse(Measurement::fromSamples([200.0, 205.0, 210.0, 215.0])->isUnstable());
    }

    /**
     * Sam iloraz nie wystarcza po optymalizacji z kroku 17: scenariusz trwający
     * 7 ms z drgnięciem 2 ms przekracza próg 1,35×, choć różnica nie może
     * wpłynąć na żadną decyzję o budżecie klatki.
     */
    public function testSmallAbsoluteSpreadIsStableEvenWithAHighRatio(): void
    {
        $measurement = Measurement::fromSamples([6.7, 7.3, 9.1]);

        self::assertGreaterThan(Measurement::UNSTABLE_SPREAD_RATIO, $measurement->spreadRatio());
        self::assertFalse($measurement->isUnstable(), 'Rozrzut 2,4 ms to szum środowiska, nie wynik.');
    }

    /** Oba progi muszą być przekroczone — sama różnica bezwzględna to za mało. */
    public function testLargeAbsoluteSpreadOnALongMeasurementStaysStableWhenTheRatioIsLow(): void
    {
        $measurement = Measurement::fromSamples([400.0, 420.0, 440.0]);

        self::assertGreaterThan(
            Measurement::UNSTABLE_SPREAD_MILLISECONDS,
            $measurement->maximum - $measurement->minimum,
        );
        self::assertFalse($measurement->isUnstable());
    }

    /** Pomiar krótszy niż rozdzielczość zegara nie ma prawa dać nieskończoności. */
    public function testZeroMinimumDoesNotProduceInfiniteSpread(): void
    {
        $measurement = Measurement::fromSamples([0.0, 5.0]);

        self::assertSame(1.0, $measurement->spreadRatio());
        self::assertFalse($measurement->isUnstable());
    }

    #[DataProvider('shares')]
    public function testShareOfWholeIsExpressedInPercent(float $part, float $whole, float $expected): void
    {
        self::assertEqualsWithDelta(
            $expected,
            Measurement::fromSamples([$part])->shareOf(Measurement::fromSamples([$whole])),
            0.01,
        );
    }

    /** @return iterable<string, array{float, float, float}> */
    public static function shares(): iterable
    {
        yield 'rysowanie z kroku 13' => [162.0, 207.0, 78.26];

        yield 'połowa' => [50.0, 100.0, 50.0];

        yield 'całość' => [100.0, 100.0, 100.0];
    }

    public function testShareOfEmptyWholeIsZeroInsteadOfDivisionByZero(): void
    {
        self::assertSame(0.0, Measurement::fromSamples([5.0])->shareOf(Measurement::fromSamples([0.0])));
    }

    public function testEmptySampleSetIsRejected(): void
    {
        try {
            Measurement::fromSamples([]);
            self::fail('Pusty zbiór próbek powinien skończyć się wyjątkiem.');
        } catch (DiagnosticsException $exception) {
            self::assertSame(DiagnosticsProblem::EmptySampleSet, $exception->problem);
        }
    }

    public function testIntegerSamplesAreAggregatedLikeFloats(): void
    {
        $measurement = Measurement::fromIntegerSamples([2, 1, 3]);

        self::assertSame(2.0, $measurement->median);
        self::assertSame(1.0, $measurement->minimum);
    }
}
