<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

use LightManager\Application\Ui\Frame;

interface FrameRendererPort
{
    /**
     * Rysuje całą klatkę od nowa, nadpisując poprzednią w tym samym miejscu
     * ekranu. Sposób rysowania (Sixel albo tekst) jest szczegółem
     * implementacji — wołający nie musi go znać.
     *
     * Klatka to stos płaszczyzn z prymitywami, więc renderer nie wie już, czym
     * jest lista plików, pasek stanu ani okno modalne. Do kroku 18 wiedział o
     * każdym z nich osobno, a razem z klatką przychodził jeszcze `FrameLayout`,
     * żeby oba rachunki podziału okna się nie rozjechały. Podział jest teraz
     * jeden i mieści się w prostokątach samych prymitywów.
     */
    public function render(Frame $frame): void;
}
