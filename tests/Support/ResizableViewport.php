<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Port\ViewportPort;

/**
 * Okno, którego rozmiar zmienia się między klatkami — odpowiednik zmiany
 * rozmiaru okna terminala w trakcie działania (krok 33). Tam, gdzie rozmiar
 * ma stać w miejscu, właściwy jest `FixedViewport`.
 */
final class ResizableViewport implements ViewportPort
{
    public function __construct(
        private int $rows = 24,
        private int $columns = 80,
    ) {
    }

    public function resize(int $rows, int $columns): void
    {
        $this->rows = $rows;
        $this->columns = $columns;
    }

    public function rows(): int
    {
        return $this->rows;
    }

    public function columns(): int
    {
        return $this->columns;
    }
}
