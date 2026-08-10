<?php

declare(strict_types=1);

namespace LightManager\Domain\ValueObject;

/**
 * Sposób, w jaki klatka trafia na ekran. Wybierany raz, przy starcie
 * aplikacji, na podstawie możliwości terminala i biblioteki graficznej.
 */
enum RendererMode
{
    case Sixel;
    case TextFallback;
}
