<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use Imagick;

/**
 * Tor, który potrafi oddać **obraz** swojej klatki, a nie tylko jej czas.
 *
 * Dwóch takich jest: sixelowy oddaje płótno Imagicka, okienkowy — bufor GPU
 * odczytany z ukrytego okna. Trzeci (tekstowy) nie rysuje obrazu w ogóle i tego
 * interfejsu nie deklaruje; jego zrzut powstaje dopiero w żywej aplikacji,
 * z rasteryzacji bufora ANSI (krok 38, D64).
 *
 * Obraz jest **zawsze tym, co zobaczy użytkownik**: w torze sixelowym po
 * kwantyzacji, bo to paleta zjadała obwódki w kroku 13, a w oknie prosto
 * z bufora, bo tam nie ma żadnego pośrednika.
 */
interface ScenarioImageSource
{
    /**
     * Obraz klatki scenariusza. Zwolnienie (`clear()`) należy do wołającego —
     * tak samo jak przy `drawCanvas()`.
     */
    public function imageOf(Scenario $scenario): Imagick;
}
