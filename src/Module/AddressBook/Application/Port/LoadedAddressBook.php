<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application\Port;

use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;

/**
 * Wynik odczytu wpisów (krok 60, wzorem `LoadedClusterBook`).
 *
 * Port nie rzuca (reguła 8), więc powód wraca kluczem katalogu. Wpis, którego
 * nie da się przyjąć, **wypada, zostawiając resztę książki** — jeden zepsuty
 * wiersz nie odbiera użytkownikowi wszystkich adresów.
 */
final readonly class LoadedAddressBook
{
    /** @param list<AddressEntry> $entries */
    public function __construct(
        public array $entries = [],
        /** Klucz katalogu z powodem; `null`, gdy odczyt się udał. */
        public ?string $problemKey = null,
    ) {
    }
}
