<?php

declare(strict_types=1);

namespace LightManager\Application\Query;

/**
 * Właściciel wyczytany z nazwy — `core.settings` należy do `core`,
 * `browser.entries` do `browser`.
 *
 * Nazwa jest jedynym miejscem, w którym własność jest zapisana, i tak ma zostać:
 * rejestr przyjmuje kwerendę **dlatego**, że nazwa zaczyna się od przedrostka
 * właściciela, więc drugie pole z tą samą informacją mogłoby się z nią rozjechać.
 * Klasa istnieje po to, żeby rozczytanie stało w jednym miejscu, a nie w każdym
 * spisie z osobna.
 */
final class Owner
{
    private function __construct()
    {
    }

    public static function of(string $name): string
    {
        $dot = strpos($name, '.');

        return $dot === false ? $name : substr($name, 0, $dot);
    }
}
