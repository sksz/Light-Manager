<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Wynik jedynej fazy, której nie da się zmierzyć bez prawdziwego terminala.
 *
 * `roundTrip` jest **przybliżeniem** i tak musi być czytany: mierzy czas od
 * rozpoczęcia zapisu klatki do odpowiedzi terminala na zapytanie DA1 wysłane
 * zaraz po niej. Terminal ma prawo odpowiedzieć, zanim domaluje obraz, więc ta
 * liczba jest dolnym oszacowaniem kosztu po jego stronie, a nie pomiarem czasu
 * wyświetlenia. `null` znaczy, że terminal nie odpowiedział w oknie czasowym —
 * wtedy kolumna zostaje pusta zamiast pokazywać wartość timeoutu jako wynik.
 */
final class TransferResult
{
    public function __construct(
        public readonly int $blobBytes,
        public readonly Measurement $writeMilliseconds,
        /** Liczba wywołań `fwrite()`, na które rozpadł się jeden zapis. */
        public readonly Measurement $chunks,
        public readonly ?Measurement $roundTripMilliseconds,
    ) {
    }

    public function throughputKilobytesPerSecond(): float
    {
        $milliseconds = $this->writeMilliseconds->median;

        return $milliseconds <= 0.0 ? 0.0 : $this->blobBytes / 1024.0 / ($milliseconds / 1000.0);
    }

    /** @return array<string, float|int|null> */
    public function toArray(): array
    {
        return [
            'bytes' => $this->blobBytes,
            'writeMs' => round($this->writeMilliseconds->median, 3),
            'chunks' => round($this->chunks->median, 1),
            'roundTripMs' => $this->roundTripMilliseconds === null
                ? null
                : round($this->roundTripMilliseconds->median, 3),
            'throughputKbPerSecond' => round($this->throughputKilobytesPerSecond(), 1),
        ];
    }
}
