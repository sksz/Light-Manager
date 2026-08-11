<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application;

use LightManager\Application\Port\TranslatorPort;

/**
 * Liczba bajtów zapisana tak, żeby dała się przeczytać.
 *
 * Klasa powstała w kroku 26 i jest **wyprowadzeniem kodu, który już istniał**,
 * a nie nową funkcją: ten sam rachunek stał do tej pory jako prywatna metoda
 * w `InspectSelectedEntryUseCase`. Trzecim wołającym miał zostać wiersz „zajęte
 * na dysku”, a trzeci wołający to moment, w którym kopiowanie przestaje być
 * tańsze od nazwania.
 *
 * Wyprowadzenie sięga **wyłącznie w obręb tego modułu**. Przeglądarka plików ma
 * własny, bliźniaczy rachunek w `EntryList` i on tutaj nie trafia — reguła 15
 * mówi, że moduł nigdy nie sięga do innego modułu, więc wspólny formatownik
 * musiałby najpierw zostać częścią rdzenia. To osobna decyzja i nie ten krok.
 *
 * Przedrostki są dziesiętne w zapisie (`kB`, `MB`), a dzielnik dwójkowy (1024) —
 * tak samo, jak przed tym krokiem. Zmiana jednego albo drugiego zmieniłaby liczby
 * na ekranie, a ten krok nie ma prawa ich zmienić.
 */
final class SizeText
{
    private const UNITS = ['B', 'kB', 'MB', 'GB', 'TB'];

    public function __construct(private readonly TranslatorPort $translator)
    {
    }

    public function format(int $bytes): string
    {
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024.0 && $unit < count(self::UNITS) - 1) {
            $value /= 1024.0;
            ++$unit;
        }

        if ($unit === 0) {
            return $this->translator->number($value) . ' ' . self::UNITS[0];
        }

        return $this->translator->number($value, 1) . ' ' . self::UNITS[$unit];
    }
}
