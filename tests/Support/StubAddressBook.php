<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\AddressBook\Application\Port\AddressBookPort;
use LightManager\Module\AddressBook\Application\Port\LoadedAddressBook;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;

/**
 * Wpisy książki adresowej w pamięci (krok 60).
 *
 * Powód ten sam, co przy `StubKubernetesState` i `StubDockerState`: test nie ma
 * prawa czytać ani pisać dokumentu stanu maszyny, na której akurat biegnie.
 * Zapisy liczą się, bo przebieg sprawdza, że wpis przeżyje uruchomienie.
 */
final class StubAddressBook implements AddressBookPort
{
    public int $saveCount = 0;

    /** @var list<AddressEntry> */
    public array $saved = [];

    /** @param list<AddressEntry> $entries */
    public function __construct(
        private array $entries = [],
        private readonly ?string $problemKey = null,
    ) {
    }

    public function load(): LoadedAddressBook
    {
        return new LoadedAddressBook($this->entries, $this->problemKey);
    }

    public function save(array $entries): void
    {
        ++$this->saveCount;
        $this->entries = $entries;
        $this->saved = $entries;
    }

    public function location(): string
    {
        return '/tmp/state.json';
    }
}
