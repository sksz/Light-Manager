<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

/**
 * Do której krawędzi kolumny dosuwa się jej treść.
 *
 * Enum leży w `Presentation`, a nie w `Application/Ui` obok `Role` i `Corner`,
 * i to jest rozstrzygnięcie warte jednego zdania: **wyrównanie nie przechodzi
 * przez port renderowania**. Tabela liczy z niego dokładną kolumnę początkową
 * napisu i oddaje `TextRun` gotowy do narysowania — renderer nigdy nie dowiaduje
 * się, że coś było do prawej dosunięte. Wartość, której port nie widzi, nie ma
 * po co stać po stronie `Application`.
 *
 * Dwa przypadki, bo tyle jest w liście plików naprawdę: nazwy i prawa czyta się
 * od lewej, liczby i daty porównuje się okiem po prawej krawędzi. Wyśrodkowania
 * nie ma, bo nie ma go do czego użyć — a komponent bez odbiorcy to API
 * zaprojektowane na domysł (reguła 13).
 */
enum Align
{
    case Left;

    case Right;
}
