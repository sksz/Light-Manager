<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

use LightManager\Domain\ValueObject\RendererMode;

interface RendererModeDetectorPort
{
    /**
     * Wynik jest ustalany raz, przy starcie aplikacji, i nie zmienia się w
     * trakcie działania procesu.
     */
    public function detect(): RendererMode;
}
