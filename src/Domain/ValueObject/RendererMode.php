<?php

declare(strict_types=1);

namespace LightManager\Domain\ValueObject;

/**
 * Sposób, w jaki klatka trafia na ekran. Wybierany raz, przy starcie
 * aplikacji: tryb okienkowy jawnie (flaga CLI, przed dotknięciem terminala),
 * tryby terminalowe — na podstawie możliwości terminala (zapytanie DA1).
 */
enum RendererMode
{
    case Sixel;
    case TextFallback;
    case OpenGl;
}
