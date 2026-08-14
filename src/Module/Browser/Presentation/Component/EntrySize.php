<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Component;

use LightManager\Application\Port\TranslatorPort;

/**
 * Rozmiar wpisu zapisany dla oka: `2,3 MB` zamiast `2411724`.
 *
 * Wydzielony w kroku 31, bo pokazują go odtąd **dwa** widoki tego samego panelu
 * — lista (`EntryList`) i drzewo (`BrowserTree`) — a dwa rachunki tej samej
 * rzeczy rozjechałyby się przy pierwszej zmianie progu albo przecinka. To ten
 * sam powód, dla którego krok 18 wyjął `ScrollWindow` z trzech miejsc naraz.
 *
 * Skróty jednostek nie idą przez katalog napisów, bo są międzynarodowe; liczbę
 * formatuje `TranslatorPort::number()`, bo separator dziesiętny już nie jest.
 */
final class EntrySize
{
    /** @var list<string> */
    private const UNITS = ['B', 'kB', 'MB', 'GB', 'TB'];

    public static function of(TranslatorPort $translator, int $bytes): string
    {
        $value = (float) $bytes;
        $unit = 0;

        while ($value >= 1024.0 && $unit < count(self::UNITS) - 1) {
            $value /= 1024.0;
            ++$unit;
        }

        if ($unit === 0) {
            return $bytes . ' B';
        }

        return $translator->number($value, 1) . ' ' . self::UNITS[$unit];
    }
}
