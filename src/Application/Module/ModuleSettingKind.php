<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Rodzaj pozycji w zakładce ustawień modułu (P12).
 *
 * Cztery rodzaje, bo tyle wystarcza na wszystko, co moduł ma dziś czym opisać —
 * i ani jednego więcej: każdy rodzaj to osobna droga w ekranie ustawień, a droga
 * bez użytkownika jest kodem napisanym na domysł (zasada P5 kroku 18).
 *
 * `Number` nie jest osobną drogą rysowania, tylko osobnym rodzajem **wartości**:
 * rysuje się tym samym polem wyboru co `Choice`, a różni się tym, że wartość
 * wraca z konfiguracji jako liczba, nie napis.
 */
enum ModuleSettingKind
{
    case Toggle;
    case Choice;
    case Number;
    case Text;
}
