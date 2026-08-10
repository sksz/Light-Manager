<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Diagnostics;

use LightManager\Infrastructure\Diagnostics\BaselineComparison;
use LightManager\Infrastructure\Diagnostics\BaselineSnapshot;
use LightManager\Infrastructure\Diagnostics\BenchmarkOptions;
use LightManager\Infrastructure\Diagnostics\EnvironmentMetadata;
use LightManager\Infrastructure\Diagnostics\Scenario;
use LightManager\Infrastructure\Diagnostics\ScenarioMedians;
use PHPUnit\Framework\TestCase;

/**
 * Porównanie z wzorcem to jedyne miejsce, w którym narzędzie samo orzeka
 * „pogorszyło się”. Reguła musi być sprawdzalna bez uruchamiania pomiaru —
 * inaczej krok 17 opierałby rozliczenie dźwigni na wydruku, którego nikt nie
 * zweryfikował.
 */
final class BaselineComparisonTest extends TestCase
{
    public function testSlowerResultAboveThresholdIsARegression(): void
    {
        $rows = BaselineComparison::between(
            $this->snapshot(['text' => 100.0]),
            $this->snapshot(['text' => 130.0]),
        );

        self::assertCount(1, $rows);
        self::assertSame(Scenario::Text, $rows[0]->scenario);
        self::assertEqualsWithDelta(30.0, $rows[0]->changePercent() ?? 0.0, 0.01);
        self::assertTrue($rows[0]->isRegression(10.0));
        self::assertCount(1, BaselineComparison::regressions($rows, 10.0));
    }

    public function testChangeBelowThresholdIsNeitherRegressionNorImprovement(): void
    {
        $rows = BaselineComparison::between(
            $this->snapshot(['text' => 100.0]),
            $this->snapshot(['text' => 105.0]),
        );

        self::assertFalse($rows[0]->isRegression(10.0));
        self::assertFalse($rows[0]->isImprovement(10.0));
    }

    public function testFasterResultIsAnImprovement(): void
    {
        $rows = BaselineComparison::between(
            $this->snapshot(['text' => 200.0]),
            $this->snapshot(['text' => 100.0]),
        );

        self::assertEqualsWithDelta(-50.0, $rows[0]->changePercent() ?? 0.0, 0.01);
        self::assertTrue($rows[0]->isImprovement(10.0));
        self::assertFalse($rows[0]->isRegression(10.0));
    }

    /**
     * Ostrzeganie o regresji na podstawie niestabilnego pomiaru byłoby fałszywym
     * alarmem — a takie alarmy uczą ignorować wszystkie.
     */
    public function testUnstableMeasurementNeverRaisesARegression(): void
    {
        $rows = BaselineComparison::between(
            $this->snapshot(['text' => 100.0]),
            $this->snapshot(['text' => 300.0], true),
        );

        self::assertTrue($rows[0]->unstable);
        self::assertFalse($rows[0]->isRegression(10.0));
        self::assertSame([], BaselineComparison::regressions($rows, 10.0));
    }

    /** Wzorzec sprzed dodania scenariusza nie jest ani regresją, ani poprawą. */
    public function testScenarioMissingFromBaselineHasNoChange(): void
    {
        $rows = BaselineComparison::between(
            $this->snapshot(['text' => 100.0]),
            $this->snapshot(['text' => 100.0, 'popup' => 250.0]),
        );

        self::assertCount(2, $rows);
        self::assertNull($rows[1]->baselineMilliseconds);
        self::assertNull($rows[1]->changePercent());
        self::assertFalse($rows[1]->isRegression(10.0));
    }

    public function testScenarioNamesWithoutCoverageInTheEnumAreSkipped(): void
    {
        $rows = BaselineComparison::between(
            $this->snapshot(['text' => 100.0]),
            $this->snapshot(['text' => 100.0, 'nieistnieje' => 10.0]),
        );

        self::assertCount(1, $rows);
    }

    public function testSnapshotsWithDifferentConfigurationAreNotComparable(): void
    {
        $baseline = new BaselineSnapshot(
            new BenchmarkOptions(paletteColors: 64),
            $this->environment(),
            [],
        );
        $current = new BaselineSnapshot(
            new BenchmarkOptions(paletteColors: 16),
            $this->environment(),
            [],
        );

        self::assertFalse($baseline->isComparableWith($current));
        self::assertTrue($baseline->isComparableWith($baseline));
    }

    /** @param array<string, float> $totals */
    private function snapshot(array $totals, bool $unstable = false): BaselineSnapshot
    {
        $scenarios = [];

        foreach ($totals as $name => $total) {
            $scenarios[$name] = new ScenarioMedians($total * 0.8, $total * 0.15, $total * 0.05, $total, 1024, $unstable);
        }

        return new BaselineSnapshot(new BenchmarkOptions(), $this->environment(), $scenarios);
    }

    private function environment(): EnvironmentMetadata
    {
        return new EnvironmentMetadata('8.3.11', 'ImageMagick 6.9', 'DejaVu-Sans-Mono', '2026-08-09T10:00:00+00:00');
    }
}
