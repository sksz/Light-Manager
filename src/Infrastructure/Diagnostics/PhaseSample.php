<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Jeden przebieg potoku: ile zajęła każda z trzech faz i ile bajtów wyszło.
 *
 * Rozmiar bloba jedzie obok czasów, bo te bajty trzeba jeszcze wypchnąć na
 * terminal — konfiguracja szybsza w liczeniu, ale dwukrotnie grubsza w zapisie,
 * nie jest szybsza wcale.
 */
final class PhaseSample
{
    public function __construct(
        public readonly float $drawMilliseconds,
        public readonly float $quantizeMilliseconds,
        public readonly float $encodeMilliseconds,
        public readonly int $blobBytes,
    ) {
    }

    public function totalMilliseconds(): float
    {
        return $this->drawMilliseconds + $this->quantizeMilliseconds + $this->encodeMilliseconds;
    }
}
