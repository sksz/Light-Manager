<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Wynik jednego scenariusza sprowadzony do liczb, które trafiają do wzorca.
 *
 * Wzorzec nie przechowuje wszystkich próbek — z porównania „przed i po” i tak
 * korzysta się przez mediany, a plik ma zostać czytelny dla człowieka. Znacznik
 * `unstable` jedzie razem z nimi, żeby porównanie umiało pominąć wiersz, którego
 * sam pomiar był niewiarygodny.
 */
final class ScenarioMedians
{
    public function __construct(
        public readonly float $drawMilliseconds,
        public readonly float $quantizeMilliseconds,
        public readonly float $encodeMilliseconds,
        public readonly float $totalMilliseconds,
        public readonly int $blobBytes,
        public readonly bool $unstable,
    ) {
    }

    /** @return array<string, float|int|bool> */
    public function toArray(): array
    {
        return [
            'draw' => round($this->drawMilliseconds, 3),
            'quantize' => round($this->quantizeMilliseconds, 3),
            'encode' => round($this->encodeMilliseconds, 3),
            'total' => round($this->totalMilliseconds, 3),
            'bytes' => $this->blobBytes,
            'unstable' => $this->unstable,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            JsonValue::float($data, 'draw'),
            JsonValue::float($data, 'quantize'),
            JsonValue::float($data, 'encode'),
            JsonValue::float($data, 'total'),
            JsonValue::int($data, 'bytes'),
            JsonValue::bool($data, 'unstable'),
        );
    }
}
