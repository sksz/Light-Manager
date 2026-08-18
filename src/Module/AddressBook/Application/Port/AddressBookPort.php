<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application\Port;

use LightManager\Module\AddressBook\Application\AddressBook;

/**
 * Książka adresowa na dysku — sekcja `address-book` dokumentu stanu (krok 60).
 *
 * Mechanizm zapisu idzie w całości z rdzenia (`StateDocumentPort`, krok 59),
 * więc portowi zostaje **treść sekcji**: co znaczą klucze i którędy wiersz staje
 * się wpisem. **Żadna ścieżka nie rzuca** (zasada portu): dokument ruszony ręcznie
 * daje pustą książkę wraz z powodem do pokazania, a nieudany zapis ginie po cichu.
 */
interface AddressBookPort
{
    public function load(): LoadedAddressBook;

    public function save(AddressBook $book): void;

    /** Gdzie dokument leży — do pokazania w górnym pasie ekranu. */
    public function location(): string;
}
