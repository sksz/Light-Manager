<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Config;

/**
 * Wynik zapisu pliku stanu (krok 59, D103).
 *
 * Trzy wartości zamiast dwóch, bo konfiguracja rozróżnia w wyjątkach katalog
 * nie do założenia od pliku nie do zapisania — a mechanizm nie ma prawa
 * spłaszczyć tej różnicy za nią.
 */
enum StateWriteOutcome
{
    case Written;

    case DirectoryFailed;

    case FileFailed;

    public function isWritten(): bool
    {
        return $this === self::Written;
    }
}
