<?php

declare(strict_types=1);

namespace LightManager\Examples\ModulPrzykladowy;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;

/**
 * Ustawienia modułu przykładowego — wzorzec dla przewodnika „Nowa pozycja
 * ustawień” (`docs/pl/przewodnik/03-jak-dodac.md`).
 *
 * Trzy rzeczy do przepisania do własnego modułu:
 *
 * 1. **Identyfikator jest jeden i pełni trzy role naraz** — klucz w pliku
 *    konfiguracyjnym (`modules.przyklad`), przedrostek napisów
 *    (`module.przyklad.`) i przestrzeń nazw komend (`przyklad.`). Stąd stała
 *    `ID` i pomocnik `key()`: nikt nie składa tych napisów ręcznie.
 * 2. **Deklaracja jest daną, nie kodem rysującym** — ekran ustawień dostaje
 *    listę `ModuleSetting` i sam ją rysuje, prowadzi po niej kursor i zapisuje
 *    wartości. Moduł nie wie, jak wygląda zakładka.
 * 3. **Wartość czyta się przez `Settings::moduleValue()`**, nigdy wprost
 *    z pliku — a odczyt dostaje nazwane znaczenie (`mowiGlosno()`), zamiast
 *    rozsypywać po module porównania z napisem `'glosny'`.
 */
final class PrzykladSettings
{
    public const ID = 'przyklad';

    /** Ton powitania: zwykły albo głośny. */
    public const TON = 'ton';

    public const TON_ZWYKLY = 'zwykly';

    public const TON_GLOSNY = 'glosny';

    private const DOMYSLNY_TON = self::TON_ZWYKLY;

    /**
     * Pozycje zakładki ustawień tego modułu.
     *
     * **Wyłącznie skalary** — prawda/fałsz, liczba z listy przystanków albo
     * napis. Lista utworów, tablica wpisów i cokolwiek zagnieżdżonego mieszka
     * we własnym pliku modułu, a nie w ustawieniach (reguła 15b).
     *
     * @return list<ModuleSetting>
     */
    public static function declarations(): array
    {
        return [
            ModuleSetting::choice(
                self::TON,
                self::key('setting.' . self::TON),
                [self::TON_ZWYKLY, self::TON_GLOSNY],
                self::DOMYSLNY_TON,
            ),
        ];
    }

    /** Czy powitanie ma być głośne; `false` znaczy ton zwykły. */
    public static function mowiGlosno(Settings $settings): bool
    {
        return $settings->moduleValue(self::ID, self::TON) === self::TON_GLOSNY;
    }

    /** Klucz katalogu napisów tego modułu — skrót używany w całym module. */
    public static function key(string $suffix): string
    {
        return 'module.' . self::ID . '.' . $suffix;
    }
}
