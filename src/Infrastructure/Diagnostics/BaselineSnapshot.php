<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Zawartość pliku wzorca — postać, w której pomiar trafia do repozytorium.
 *
 * Wzorce leżą w `docs/pomiary/` z datą w nazwie, więc historia wydajności jest
 * częścią repozytorium tak samo jak historia decyzji. Krok 17 (optymalizacja)
 * ma z czym porównać każdą swoją dźwignię, zamiast opierać rozliczenie „przed i
 * po” na wrażeniach.
 */
final class BaselineSnapshot
{
    /**
     * @param array<string, ScenarioMedians> $scenarios klucz: wartość `Scenario`
     */
    public function __construct(
        public readonly BenchmarkOptions $options,
        public readonly EnvironmentMetadata $environment,
        public readonly array $scenarios,
        public readonly ?TransferResult $transfer = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $scenarios = [];

        foreach ($this->scenarios as $name => $medians) {
            $scenarios[$name] = $medians->toArray();
        }

        return [
            'signature' => $this->options->signature(),
            'environment' => $this->environment->toArray(),
            'options' => $this->options->toArray(),
            'scenarios' => $scenarios,
            'transfer' => $this->transfer?->toArray(),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $scenarios = [];

        /** @var mixed $entry */
        foreach (JsonValue::map($data, 'scenarios') as $name => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $medians = [];

            /** @var mixed $value */
            foreach ($entry as $key => $value) {
                if (is_string($key)) {
                    $medians[$key] = $value;
                }
            }

            $scenarios[$name] = ScenarioMedians::fromArray($medians);
        }

        return new self(
            BenchmarkOptions::fromArray(JsonValue::map($data, 'options')),
            EnvironmentMetadata::fromArray(JsonValue::map($data, 'environment')),
            $scenarios,
        );
    }

    /**
     * Czy dwa wzorce w ogóle wolno zestawiać. Inna konfiguracja znaczy inny
     * pomiar — porównanie pokazałoby wtedy różnicę ustawień, nie zmianę kodu.
     */
    public function isComparableWith(self $other): bool
    {
        return $this->options->signature() === $other->options->signature();
    }
}
