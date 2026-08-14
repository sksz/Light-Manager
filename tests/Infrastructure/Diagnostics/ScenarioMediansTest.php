<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Diagnostics;

use LightManager\Infrastructure\Diagnostics\ScenarioMedians;
use PHPUnit\Framework\TestCase;

/**
 * Wzorce leżą w repozytorium od kroku 16, a kolumny dochodzą do nich później —
 * więc odczyt musi znieść plik starszy od kodu, który go czyta.
 */
final class ScenarioMediansTest extends TestCase
{
    public function testEveryColumnSurvivesTheRoundTripThroughJson(): void
    {
        $medians = new ScenarioMedians(12.5, 3.25, 1.5, 17.25, 20480, false, 401.5, 48 * 1024 * 1024);

        $restored = ScenarioMedians::fromArray($medians->toArray());

        self::assertSame(17.25, $restored->totalMilliseconds);
        self::assertSame(401.5, $restored->coldMilliseconds);
        self::assertSame(48 * 1024 * 1024, $restored->peakMemoryBytes);
    }

    /**
     * Wzorzec sprzed kroku 38 nie ma kolumny zimnej klatki. **Brak znaczy „nie
     * zmierzono”, a nie zero** — zero czytałoby się jako „klatka nie kosztowała
     * nic” i psuło każde porównanie, w którym ktoś by na tę kolumnę spojrzał.
     */
    public function testBaselineFromBeforeTheColdColumnReadsAsNotMeasured(): void
    {
        $medians = ScenarioMedians::fromArray([
            'draw' => 40.0,
            'quantize' => 9.0,
            'encode' => 5.0,
            'total' => 54.0,
            'bytes' => 30000,
            'unstable' => false,
        ]);

        self::assertNull($medians->coldMilliseconds);
        self::assertSame(0, $medians->peakMemoryBytes);
        self::assertSame(54.0, $medians->totalMilliseconds);
    }
}
