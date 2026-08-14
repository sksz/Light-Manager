<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Port\ViewportPort;

/**
 * Okno o rozmiarze wziętym z osi `--grid`, a nie z terminala.
 *
 * Pomiar ma być powtarzalny, więc rozmiar nie może zależeć od tego, jak duże
 * okno akurat ma powłoka — to ta sama zasada, dla której treść klatek bierze się
 * z licznika, a nie z katalogu na dysku.
 */
final class FixedViewport implements ViewportPort
{
    public function __construct(
        private readonly int $rows,
        private readonly int $columns,
    ) {
    }

    public function rows(): int
    {
        return max(1, $this->rows);
    }

    public function columns(): int
    {
        return max(1, $this->columns);
    }
}
