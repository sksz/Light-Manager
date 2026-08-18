<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;

/**
 * Wszystko, co ekran książki wie o niej — **jednym obiektem** (krok 60).
 *
 * Powstał z tego samego zderzenia, co `HostBookView` w kroku 54: „rejestr
 * jedyną drogą odczytu" wobec ekranu, który czyta o książce cztery rzeczy —
 * spis, rozdziały, położenie dokumentu i powód, dla którego dokumentu nie dało
 * się przeczytać. Cztery kwerendy na jedno pytanie byłyby czterema pokoleniami
 * do pilnowania, a odpowiedź i tak pochodzi z jednego odczytu dysku.
 *
 * Ładunek wychodzi **wyłącznie do właściciela**, więc obcemu ani położenie
 * dokumentu, ani spis rozdziałów się nie należy — w wierszach stoją same wpisy.
 */
final readonly class AddressBookView
{
    /**
     * @param list<AddressEntry>  $entries  wpisy w kolejności wybranej w ustawieniach
     * @param list<AddressChapter> $chapters rozdziały wraz z polami
     */
    public function __construct(
        public array $entries,
        public array $chapters,
        /** Ścieżka dokumentu, w którym książka mieszka — pokazuje ją górny pas. */
        public string $location,
        /** Klucz katalogu z powodem, gdy dokumentu nie dało się przeczytać. */
        public ?string $problemKey = null,
    ) {
    }

    /** Odpowiedź zastępcza fasady, gdy kwerendy nie ma kto wykonać (reguła 8). */
    public static function empty(): self
    {
        return new self([], [], '');
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function at(int $index): ?AddressEntry
    {
        return $this->entries[$index] ?? null;
    }

    public function find(string $id): ?AddressEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }

    /** Numer wpisu o tym identyfikatorze na pokazywanej liście; `null`, gdy go nie ma. */
    public function indexOf(string $id): ?int
    {
        foreach ($this->entries as $index => $entry) {
            if ($entry->id === $id) {
                return $index;
            }
        }

        return null;
    }
}
