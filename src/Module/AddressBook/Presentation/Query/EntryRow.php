<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Query;

use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;

/**
 * Wpis zamieniony na wiersz danych pierwotnych — **jedno miejsce dla obu
 * kwerend** (krok 60).
 *
 * Osobna klasa, bo rachunek mają dwa wołające (`address-book.entries`
 * i `address-book.entry`), a dwa rachunki rozjechałyby się przy pierwszym
 * dołożonym polu (11n). Wartości rozdziałów idą **spłaszczone**
 * (`<rozdział>.<pole>`), bo wiersz kwerendy jest płaską mapą skalarów i innej
 * postaci mieć nie może (reguła 15g).
 */
final class EntryRow
{
    /**
     * @param list<string> $chapters rozdziały do dołożenia; pusta lista — same pola wspólne
     *
     * @return array<string, string|int|bool>
     */
    public static function of(AddressEntry $entry, array $chapters): array
    {
        $row = [
            'id' => $entry->id,
            'name' => $entry->name,
            'address' => $entry->address,
        ];

        foreach ($chapters as $chapter) {
            foreach ($entry->chapter($chapter) as $key => $value) {
                $row[$chapter . '.' . $key] = $value;
            }
        }

        return $row;
    }

    /** @return list<string> wszystkie rozdziały, w których wpis ma wartości */
    public static function chaptersOf(AddressEntry $entry): array
    {
        return array_keys($entry->values);
    }
}
