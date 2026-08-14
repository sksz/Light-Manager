<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Komplet pomiarów jednego scenariusza: każda faza osobno i suma.
 *
 * Suma liczona jest z **sum poszczególnych przebiegów**, a nie z dodania trzech
 * median — te ostatnie pochodzą z różnych przebiegów, więc ich suma nie opisuje
 * żadnej istniejącej klatki.
 */
final class ScenarioResult
{
    public function __construct(
        public readonly Scenario $scenario,
        public readonly Measurement $draw,
        public readonly Measurement $quantize,
        public readonly Measurement $encode,
        public readonly Measurement $total,
        public readonly Measurement $blobBytes,
        /**
         * Pierwsza klatka rozgrzewki — koszt **zimny**, płacony przy starcie
         * aplikacji i po każdej zmianie rozmiaru okna (krok 38, D64).
         *
         * `null`, gdy przebieg szedł bez rozgrzewki (`--warmup=0`): zera
         * w tym miejscu byłyby kłamstwem, a nie brakiem pomiaru.
         */
        public readonly ?PhaseSample $cold = null,
        /**
         * Szczyt pamięci procesu **w obrębie tego scenariusza** — licznik jest
         * zerowany przed próbkami, więc liczba nie niesie ze sobą szczytu
         * poprzednich scenariuszy.
         */
        public readonly int $peakMemoryBytes = 0,
    ) {
    }

    /**
     * @param list<PhaseSample> $samples
     * @param ?PhaseSample      $cold    pierwsza próbka rozgrzewki, jeśli była
     *
     * @throws DiagnosticsException gdy scenariusz nie dostarczył ani jednej próbki
     */
    public static function fromSamples(
        Scenario $scenario,
        array $samples,
        ?PhaseSample $cold = null,
        int $peakMemoryBytes = 0,
    ): self {
        return new self(
            $scenario,
            Measurement::fromSamples(array_map(
                static fn (PhaseSample $sample): float => $sample->drawMilliseconds,
                $samples,
            )),
            Measurement::fromSamples(array_map(
                static fn (PhaseSample $sample): float => $sample->quantizeMilliseconds,
                $samples,
            )),
            Measurement::fromSamples(array_map(
                static fn (PhaseSample $sample): float => $sample->encodeMilliseconds,
                $samples,
            )),
            Measurement::fromSamples(array_map(
                static fn (PhaseSample $sample): float => $sample->totalMilliseconds(),
                $samples,
            )),
            Measurement::fromIntegerSamples(array_map(
                static fn (PhaseSample $sample): int => $sample->blobBytes,
                $samples,
            )),
            $cold,
            $peakMemoryBytes,
        );
    }

    public function isUnstable(): bool
    {
        return $this->total->isUnstable();
    }

    public function medians(): ScenarioMedians
    {
        return new ScenarioMedians(
            $this->draw->median,
            $this->quantize->median,
            $this->encode->median,
            $this->total->median,
            (int) round($this->blobBytes->median),
            $this->isUnstable(),
            $this->cold?->totalMilliseconds(),
            $this->peakMemoryBytes,
        );
    }
}
