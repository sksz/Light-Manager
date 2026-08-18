<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application\Port;

use LightManager\Module\AddressBook\Application\AddressBook;

/**
 * Książka wraz z tym, co poszło nie tak przy jej czytaniu (krok 60).
 *
 * Wzorem `LoadedHostBook` z kroku 48: port nie rzuca, więc powód niepowodzenia
 * musi mieć czym wrócić. Rozdzielenie „pusta książka" od „książki nie dało się
 * przeczytać" jest istotne, a nie kosmetyczne — pierwsze jest normalnym stanem
 * pierwszego uruchomienia, drugie znaczy, że **zapis skasuje cudzą treść**.
 */
final readonly class LoadedAddressBook
{
    public function __construct(
        public AddressBook $book,
        /** Klucz katalogu z powodem; `null`, gdy odczyt się udał. */
        public ?string $problemKey = null,
    ) {
    }
}
