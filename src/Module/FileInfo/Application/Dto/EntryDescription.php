<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Dto;

/**
 * Opis zaznaczonego wpisu: co pokazać i w ilu wierszach.
 *
 * Do kroku 18 przypadek użycia oddawał wprost `Popup` z `Domain` — czyli obiekt
 * opisujący **wygląd** okienka. Nie miał do tego prawa: warstwa aplikacji zna
 * treść, a nie kształt, w jakim treść wyląduje na ekranie. Dziś oddaje same
 * dane, a ekran modułu składa z nich panel.
 *
 * Klasa mieszkała w `Application/Dto` rdzenia do kroku 20; zeszła do modułu
 * wraz z całą resztą opisu pliku — w rdzeniu nie ma po nim ani jednej klasy.
 */
final class EntryDescription
{
    /** @param list<string> $lines */
    public function __construct(
        public readonly string $name,
        public readonly array $lines,
    ) {
    }
}
