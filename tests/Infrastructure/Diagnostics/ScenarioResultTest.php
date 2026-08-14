<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Diagnostics;

use LightManager\Infrastructure\Diagnostics\PhaseSample;
use LightManager\Infrastructure\Diagnostics\Scenario;
use LightManager\Infrastructure\Diagnostics\ScenarioResult;
use PHPUnit\Framework\TestCase;

final class ScenarioResultTest extends TestCase
{
    public function testEachPhaseIsAggregatedSeparately(): void
    {
        $result = ScenarioResult::fromSamples(Scenario::Text, [
            new PhaseSample(100.0, 40.0, 5.0, 20000),
            new PhaseSample(200.0, 20.0, 5.0, 20000),
            new PhaseSample(150.0, 30.0, 5.0, 20000),
        ]);

        self::assertSame(150.0, $result->draw->median);
        self::assertSame(30.0, $result->quantize->median);
        self::assertSame(5.0, $result->encode->median);
    }

    /**
     * Suma musi pochodzić z sum poszczególnych przebiegów, a nie z dodania
     * trzech median — te ostatnie pochodzą z różnych przebiegów, więc ich suma
     * nie opisuje żadnej istniejącej klatki.
     */
    public function testTotalComesFromPerRunSumsNotFromAddedMedians(): void
    {
        $result = ScenarioResult::fromSamples(Scenario::Text, [
            new PhaseSample(100.0, 50.0, 1.0, 100),
            new PhaseSample(200.0, 10.0, 1.0, 100),
            new PhaseSample(120.0, 30.0, 1.0, 100),
        ]);

        // Mediany faz: 120 + 30 + 1 = 151. Sumy przebiegów: 151, 211, 151.
        self::assertSame(151.0, $result->total->median);
        self::assertSame(211.0, $result->total->maximum);
    }

    public function testUnstableTotalMarksTheWholeScenario(): void
    {
        $result = ScenarioResult::fromSamples(Scenario::ChromeWithText, [
            new PhaseSample(184.0, 0.0, 0.0, 100),
            new PhaseSample(254.0, 0.0, 0.0, 100),
        ]);

        self::assertTrue($result->isUnstable());
        self::assertTrue($result->medians()->unstable);
    }

    /**
     * Zimna klatka jedzie **obok** mediany, a nie w niej: gdyby weszła do
     * próbek, podniosłaby medianę o koszt, którego ustalona klatka nie płaci.
     */
    public function testColdFrameTravelsBesideTheMedianAndNotInsideIt(): void
    {
        $result = ScenarioResult::fromSamples(
            Scenario::Thumbnail,
            [
                new PhaseSample(10.0, 1.0, 1.0, 100),
                new PhaseSample(10.0, 1.0, 1.0, 100),
                new PhaseSample(10.0, 1.0, 1.0, 100),
            ],
            new PhaseSample(387.0, 5.0, 8.0, 100),
            48 * 1024 * 1024,
        );

        self::assertSame(12.0, $result->total->median);
        self::assertSame(400.0, $result->medians()->coldMilliseconds);
        self::assertSame(48 * 1024 * 1024, $result->medians()->peakMemoryBytes);
    }

    /** Przebieg bez rozgrzewki nie ma zimnej klatki — i ma to powiedzieć wprost. */
    public function testWithoutWarmupThereIsNoColdFrameAtAll(): void
    {
        $medians = ScenarioResult::fromSamples(Scenario::Text, [new PhaseSample(1.0, 1.0, 1.0, 10)])->medians();

        self::assertNull($medians->coldMilliseconds);
        self::assertNull($medians->toArray()['cold']);
    }

    public function testMediansCarryTheBlobSizeAsWholeBytes(): void
    {
        $medians = ScenarioResult::fromSamples(Scenario::Popup, [
            new PhaseSample(1.0, 1.0, 1.0, 1000),
            new PhaseSample(1.0, 1.0, 1.0, 1001),
            new PhaseSample(1.0, 1.0, 1.0, 1002),
        ])->medians();

        self::assertSame(1001, $medians->blobBytes);
        self::assertSame(3.0, $medians->totalMilliseconds);
    }
}
