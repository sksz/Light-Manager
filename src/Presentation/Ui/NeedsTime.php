<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Element interfejsu, którego wygląd zależy od czasu.
 *
 * Dziś jest jeden taki: pole tekstowe, w którym mruga karetka. Zegar przychodzi
 * z zewnątrz, bo `microtime()` w komponencie zamieniłoby go w coś, czego nie da
 * się przetestować bez czekania — a tak test podaje własną chwilę i sprawdza
 * karetkę w obu stanach.
 *
 * Osobny interfejs, a nie metoda w `OverlayInterface`, bo okno z opisem pliku
 * nie ma czego robić z czasem i nie ma powodu tego deklarować (ten sam wzorzec
 * co `Resettable`).
 */
interface NeedsTime
{
    public function useTime(float $now): void;
}
