<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation\Component;

use LightManager\Application\Port\TranslatorPort;

/**
 * Rozmiar zapisany dla oka — `2,3 MB` zamiast `2411724` (krok 50).
 *
 * Wydzielony z `RemoteScreen` z tego samego powodu, dla którego krok 31 wydzielił
 * `EntrySize` w przeglądarce: rachunek ma **dwóch** użytkowników w tym module —
 * kolumnę listy i licznik okna postępu — a dwa zapisy tej samej liczby
 * rozjechałyby się przy pierwszej zmianie progu albo przecinka.
 *
 * Powtórzeniem wobec `EntrySize` **jest** i jest to powtórzenie świadome: tamten
 * mieszka w cudzym module, a reguła 15 zabrania po niego sięgać. Granica z reguły
 * 15e jest tu spełniona wprost — powtarzamy **pojęcie** (rozmiar dla oka), tanie
 * i bez skutków ubocznych, a nie mechanizm rdzenia.
 *
 * Skróty jednostek nie idą przez katalog napisów, bo są międzynarodowe; liczbę
 * formatuje `TranslatorPort::number()`, bo separator dziesiętny już nie jest.
 */
final class RemoteSize
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
