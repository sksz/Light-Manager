<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application\Port;

use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;

/**
 * Zapis i odczyt wpisów — sekcja `address-book` dokumentu stanu (krok 60).
 *
 * **Wpis leży tu w całości**, razem z wartościami wszystkich rozdziałów
 * i z polami maskowanymi (D104 nr 6) — to jest ta wada usuniętej książki,
 * w której adres stał w jednej sekcji, a poświadczenia w drugiej. Sekcje
 * modułów trzymają odtąd wskaźniki i pamięć podręczną, nigdy pola wpisu.
 *
 * **Rozdziałów port nie zapisuje** i nie ma takiej metody: deklaracje powstają
 * przy każdym uruchomieniu, a na dysku zostają wyłącznie **wartości**.
 *
 * Żadna ścieżka nie rzuca (zasada portu, krok 14): sekcja nieczytelna wraca
 * pustą książką z kluczem powodu.
 */
interface AddressBookPort
{
    public function load(): LoadedAddressBook;

    /** @param list<AddressEntry> $entries */
    public function save(array $entries): void;

    /** Gdzie dokument leży — ekran pokazuje to, gdy książka jest pusta. */
    public function location(): string;
}
