<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;

/**
 * Migawka wpisów — ładunek kwerendy `address-book.entries` (krok 60).
 *
 * **Migawka, nie obiekt roboczy** (reguła 11w): `Addresses` jest mutowalne
 * i ładowane leniwie, więc oddanie go wprost rozsiałoby po ekranie obsługę
 * stanu „jeszcze nie wczytano". Ładunek widzi wyłącznie fasada modułu książki,
 * a wszyscy pozostali — wiersze; różnica dotyczy **typów, nie dostępu do
 * danych** (D104 nr 1).
 */
final readonly class AddressBookView
{
    /** @param list<AddressEntry> $entries */
    public function __construct(
        public array $entries,
        /** Gdzie leży dokument stanu — do pokazania, gdy książka jest pusta. */
        public string $location,
        /** Klucz katalogu z powodem, dla którego wpisów nie widać. */
        public ?string $problemKey = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
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
}
