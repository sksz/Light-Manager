<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Jeden wiersz porównania z wzorcem.
 *
 * `null` w `baselineMilliseconds` znaczy, że wzorzec nie zawierał tego
 * scenariusza (doszedł później) — to nie regresja i nie poprawa, tylko brak
 * punktu odniesienia, i tak ma zostać pokazane.
 */
final class ComparisonRow
{
    public function __construct(
        public readonly Scenario $scenario,
        public readonly ?float $baselineMilliseconds,
        public readonly float $currentMilliseconds,
        /** Czy któryś z dwóch pomiarów był oznaczony jako niestabilny. */
        public readonly bool $unstable,
    ) {
    }

    /** Zmiana w procentach; dodatnia znaczy „wolniej niż we wzorcu”. */
    public function changePercent(): ?float
    {
        if ($this->baselineMilliseconds === null || $this->baselineMilliseconds <= 0.0) {
            return null;
        }

        return ($this->currentMilliseconds - $this->baselineMilliseconds) / $this->baselineMilliseconds * 100.0;
    }

    /**
     * Regresja to pogorszenie powyżej progu — ale nie wtedy, gdy którykolwiek
     * z pomiarów sam był niewiarygodny. Ostrzeganie o regresji na podstawie
     * próbki rozrzuconej 184–254 ms byłoby fałszywym alarmem, a takie alarmy
     * uczą ignorować wszystkie.
     */
    public function isRegression(float $thresholdPercent): bool
    {
        $change = $this->changePercent();

        return !$this->unstable && $change !== null && $change > $thresholdPercent;
    }

    public function isImprovement(float $thresholdPercent): bool
    {
        $change = $this->changePercent();

        return !$this->unstable && $change !== null && $change < -$thresholdPercent;
    }
}
