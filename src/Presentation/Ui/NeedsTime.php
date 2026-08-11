<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Element interfejsu, którego wygląd zależy od czasu.
 *
 * Dwa takie są: pole tekstowe, w którym mruga karetka, i pasek postępu, który
 * nie zna postępu — jego wypełnienie wędruje tam i z powrotem. Zegar przychodzi
 * z zewnątrz, bo `microtime()` w komponencie zamieniłoby go w coś, czego nie da
 * się przetestować bez czekania — a tak test podaje własną chwilę i sprawdza
 * karetkę w obu stanach, a pasek w dowolnym miejscu cyklu.
 *
 * Osobny interfejs, a nie metoda w `OverlayInterface`, bo okno z opisem pliku
 * nie ma czego robić z czasem i nie ma powodu tego deklarować (ten sam wzorzec
 * co `Resettable`). Od kroku 23 pyta o niego także **ekran** — składanie klatki
 * sprawdza jedno i drugie. To była jedyna droga, przy której `ScreenInterface`
 * nie musiał urosnąć po raz drugi od kroku 18.
 */
interface NeedsTime
{
    public function useTime(float $now): void;
}
