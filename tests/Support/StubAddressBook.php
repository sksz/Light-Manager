<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\AddressBook\Application\AddressBook;
use LightManager\Module\AddressBook\Application\Port\AddressBookPort;
use LightManager\Module\AddressBook\Application\Port\LoadedAddressBook;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;

/**
 * Książka adresowa w pamięci (krok 60).
 *
 * Powód ten sam, co przy `StubSshState`, `StubEnvironmentBook`
 * i `StubClusterBook`: test nie ma prawa czytać ani pisać dokumentu stanu
 * w katalogu domowym osoby, która go uruchamia.
 *
 * `entry()` nadaje identyfikatory **przewidywalne** (`entry-1`… nie przejdzie
 * walidacji, więc idą ósemki szesnastkowe z licznikiem) — przebieg sprawdzający
 * „wpis dopisany widać w spisie hostów" musi umieć wskazać ten sam wpis dwa
 * razy, a losowy identyfikator by mu to odebrał.
 */
final class StubAddressBook implements AddressBookPort
{
    public int $saves = 0;

    private static int $counter = 0;

    public function __construct(
        private AddressBook $book = new AddressBook(),
        private readonly ?string $problemKey = null,
    ) {
    }

    /**
     * Wpis o przewidywalnym identyfikatorze — do składania książek w przebiegach.
     *
     * @param array<string, array<string, string|int|bool>> $values rozdział → pole → wartość
     */
    public static function entry(string $name, string $address, array $values = []): AddressEntry
    {
        return new AddressEntry(sprintf('%08x', ++self::$counter), $name, $address, $values);
    }

    public function load(): LoadedAddressBook
    {
        return new LoadedAddressBook($this->book, $this->problemKey);
    }

    public function save(AddressBook $book): void
    {
        ++$this->saves;
        $this->book = $book;
    }

    public function location(): string
    {
        return '/tmp/light-manager-test/state.json';
    }
}
