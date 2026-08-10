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
    ) {
    }

    /**
     * @param list<PhaseSample> $samples
     *
     * @throws DiagnosticsException gdy scenariusz nie dostarczył ani jednej próbki
     */
    public static function fromSamples(Scenario $scenario, array $samples): self
    {
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
        );
    }
}
