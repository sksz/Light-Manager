<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation;

use LightManager\Module\AddressBook\Application\ChapterField;

/**
 * Podpowiedzi argumentów komend książki — **jeden rachunek dla ośmiu komend**
 * (krok 60).
 *
 * Stoi osobno, bo identyfikatory, rozdziały i pola podpowiada tu prawie każda
 * komenda, a osiem kopii tego samego przeszukiwania rozjechałoby się przy
 * pierwszej zmianie tego, co pokazujemy obok identyfikatora.
 *
 * Identyfikator idzie **z nazwą obok** i to jest cała treść tej klasy:
 * dwunastu znaków szesnastkowych nikt nie pamięta, a komenda przyjmuje właśnie
 * je. Rejestr komend bierze z podpowiedzi **pierwsze słowo**, więc nazwa
 * w nawiasie jest dla oka, a nie dla parsera.
 */
final class Suggestions
{
    /** @return list<string> */
    public static function entries(AddressBookQueries $reader, string $prefix): array
    {
        $matching = [];

        foreach ($reader->book()->entries as $entry) {
            if ($prefix === '' || stripos($entry->id, $prefix) === 0 || stripos($entry->name, $prefix) === 0) {
                $matching[] = $entry->name === '' ? $entry->id : $entry->id . ' (' . $entry->name . ')';
            }
        }

        return $matching;
    }

    /** @return list<string> */
    public static function chapters(AddressBookQueries $reader, string $prefix): array
    {
        return self::matching($reader->chapters()->names(), $prefix);
    }

    /** @return list<string> */
    public static function fields(AddressBookQueries $reader, string $chapter, string $prefix): array
    {
        return self::matching($reader->fields($chapter)->keys(), $prefix);
    }

    /** @return list<string> dopuszczalne wartości pola, gdy rodzaj je wypisuje */
    public static function values(AddressBookQueries $reader, string $chapter, string $field, string $prefix): array
    {
        $declared = $reader->fields($chapter)->field($field);

        return $declared instanceof ChapterField ? self::matching($declared->choices, $prefix) : [];
    }

    /**
     * Identyfikator wyłuskany z podpowiedzi — pierwsze słowo, bo reszta jest
     * nazwą pokazaną dla oka.
     */
    public static function idOf(string $value): string
    {
        $trimmed = trim($value);
        $space = strpos($trimmed, ' ');

        return $space === false ? $trimmed : substr($trimmed, 0, $space);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function matching(array $values, string $prefix): array
    {
        if ($prefix === '') {
            return $values;
        }

        return array_values(array_filter(
            $values,
            static fn (string $value): bool => stripos($value, $prefix) === 0,
        ));
    }
}
