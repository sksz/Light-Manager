<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Co wskaźnik zrobił (krok 55).
 *
 * `Drag` to ruch **przy wciśniętym przycisku** i tylko taki — raportowanie
 * włącza się trybem `1002`, a nie `1003`, więc ruch z podniesionym przyciskiem
 * nie przychodzi w ogóle. Tor okienkowy odrzuca go w wywołaniu zwrotnym, żeby
 * zachowywał się tak samo, a nie „podobnie”.
 *
 * `ScrollUp` i `ScrollDown` nie niosą przycisku sensownie — pole `button`
 * `PointerEvent`u ma przy nich wartość `Left`, bo protokół podaje kółko na tych
 * samych bitach, co przyciski, a odbiorca kółka o przycisk nie pyta.
 */
enum PointerAction
{
    case Press;
    case Release;
    case Drag;
    case ScrollUp;
    case ScrollDown;
}
