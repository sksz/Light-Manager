<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Port\ViewportPort;

final class FixedViewport implements ViewportPort
{
    public function __construct(
        private readonly int $rows = 24,
        private readonly int $columns = 80,
    ) {
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
