<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Zestawia bieżący przebieg z zapisanym wzorcem.
 *
 * Klasa jest czysta — dostaje dwa `BaselineSnapshot`, oddaje wiersze różnic —
 * więc reguła „co jest regresją” daje się sprawdzić testem, a nie tylko obejrzeć
 * na wydruku. Próg jest parametrem, nie stałą wpisaną w porównanie: dla klatki
 * liczonej w setkach milisekund co innego znaczy 10%, a co innego dla fazy
 * kodowania trwającej 5 ms.
 */
final class BaselineComparison
{
    /**
     * Domyślny próg regresji. Świadomie wyższy niż typowy szum pomiaru — przy
     * rozrzucie zaobserwowanym w kroku 13 niższy próg zapalałby się bez powodu.
     */
    public const DEFAULT_THRESHOLD_PERCENT = 10.0;

    /** @return list<ComparisonRow> w kolejności scenariuszy bieżącego przebiegu */
    public static function between(BaselineSnapshot $baseline, BaselineSnapshot $current): array
    {
        $rows = [];

        foreach ($current->scenarios as $name => $medians) {
            $scenario = Scenario::tryFrom($name);

            if ($scenario === null) {
                continue;
            }

            $reference = $baseline->scenarios[$name] ?? null;

            $rows[] = new ComparisonRow(
                $scenario,
                $reference?->totalMilliseconds,
                $medians->totalMilliseconds,
                $medians->unstable || ($reference !== null && $reference->unstable),
            );
        }

        return $rows;
    }

    /**
     * @param list<ComparisonRow> $rows
     *
     * @return list<ComparisonRow> wyłącznie te, które pogorszyły się ponad próg
     */
    public static function regressions(array $rows, float $thresholdPercent = self::DEFAULT_THRESHOLD_PERCENT): array
    {
        return array_values(array_filter(
            $rows,
            static fn (ComparisonRow $row): bool => $row->isRegression($thresholdPercent),
        ));
    }
}
