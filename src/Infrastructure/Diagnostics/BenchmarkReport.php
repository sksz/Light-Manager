<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Komplet jednego przebiegu: konfiguracja, środowisko, wyniki scenariuszy i —
 * jeśli był terminal — pomiar przesyłu.
 *
 * To ten obiekt idzie na wydruk i do pliku wzorca. Wzorzec zapisuje z niego
 * `BaselineSnapshot`, czyli mediany bez surowych próbek: porównanie „przed i po”
 * i tak korzysta z median, a plik ma zostać czytelny dla człowieka zaglądającego
 * do `docs/pomiary/` po pół roku.
 */
final class BenchmarkReport
{
    /**
     * @param list<ScenarioResult> $results
     */
    public function __construct(
        public readonly BenchmarkOptions $options,
        public readonly EnvironmentMetadata $environment,
        public readonly array $results,
        public readonly ?TransferResult $transfer = null,
    ) {
    }

    public function withTransfer(?TransferResult $transfer): self
    {
        return new self($this->options, $this->environment, $this->results, $transfer);
    }

    public function toSnapshot(): BaselineSnapshot
    {
        $scenarios = [];

        foreach ($this->results as $result) {
            $scenarios[$result->scenario->value] = $result->medians();
        }

        return new BaselineSnapshot(
            $this->options,
            $this->environment,
            $scenarios,
            $this->transfer,
        );
    }

    /** Czy którykolwiek scenariusz wyszedł niestabilny — wtedy wzorzec jest podejrzany. */
    public function hasUnstableResults(): bool
    {
        foreach ($this->results as $result) {
            if ($result->isUnstable()) {
                return true;
            }
        }

        return false;
    }
}
