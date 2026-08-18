<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\AddressBook;

use LightManager\Module\AddressBook\Domain\Exception\InvalidAddressEntryException;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use PHPUnit\Framework\TestCase;

/**
 * Wpis książki adresowej — pojemnik z własną tożsamością (krok 60).
 *
 * Testy pilnują trzech rzeczy, które ten krok odwrócił wobec książki hostów:
 * **tożsamością jest identyfikator**, **nazwa i adres wolno mieć puste**,
 * a **pola rozdziałów są nieprzezroczyste** — wpis nie wie, co znaczą.
 */
final class AddressEntryTest extends TestCase
{
    public function testIdentityIsTheIdentifierAndNotTheName(): void
    {
        $first = new AddressEntry('0000000a', 'biuro', 'example.com');
        $second = new AddressEntry('0000000b', 'biuro', 'example.com');

        self::assertFalse($first->equals($second), 'dwa wpisy o tej samej nazwie to dwa wpisy');
        self::assertTrue($first->equals($first->withName('inaczej')->withAddress('10.0.0.5')));
    }

    /** Nazwa i adres wolno mieć puste — wpis powstaje, a spis pokazuje, co ma. */
    public function testNameAndAddressMayBeEmpty(): void
    {
        $entry = new AddressEntry('0000000a');

        self::assertSame('', $entry->name);
        self::assertSame('', $entry->address);
        self::assertSame('0000000a', $entry->label(), 'bez nazwy widać identyfikator');
    }

    public function testIdentifierMustBeEightHexadecimalCharacters(): void
    {
        $this->expectException(InvalidAddressEntryException::class);

        new AddressEntry('biuro');
    }

    /**
     * **Adres zaczynający się od myślnika jest odrzucany** — i jest to lekcja
     * kroku 48 wzięta wprost: `ssh` przeczytałby taką wartość jako opcję,
     * niezależnie od tego, jak dokładnie zacytowała ją powłoka.
     */
    public function testAnAddressStartingWithADashIsRefused(): void
    {
        $this->expectException(InvalidAddressEntryException::class);

        new AddressEntry('0000000a', 'podstęp', '-oProxyCommand=touch /tmp/ups');
    }

    public function testAnAddressWithASpaceIsRefused(): void
    {
        $this->expectException(InvalidAddressEntryException::class);

        new AddressEntry('0000000a', 'biuro', 'example.com /etc/passwd');
    }

    /** Wartości rozdziału są nieprzezroczyste, ale ich klucze — nie dowolne. */
    public function testChapterKeysMustLookLikeModuleIdentifiers(): void
    {
        $this->expectException(InvalidAddressEntryException::class);

        new AddressEntry('0000000a', 'biuro', 'example.com', ['Ssh Moduł' => ['port' => 22]]);
    }

    public function testChapterValuesAreKeptPerChapter(): void
    {
        $entry = (new AddressEntry('0000000a', 'biuro', 'example.com'))
            ->withValue('ssh', 'port', 2222)
            ->withValue('ssh', 'user', 'anna')
            ->withValue('db', 'engine', 'pgsql');

        self::assertSame(['port' => 2222, 'user' => 'anna'], $entry->chapter('ssh'));
        self::assertSame('pgsql', $entry->value('db', 'engine'));
        self::assertNull($entry->value('ssh', 'nieznane'));
        self::assertSame([], $entry->withoutChapter('db')->chapter('db'));
    }

    /** Zawężenie szuka po nazwie, adresie **i identyfikatorze** — bo ten wpisuje się w komendach. */
    public function testFilterLooksAtNameAddressAndIdentifier(): void
    {
        $entry = new AddressEntry('0000abcd', 'biuro', 'example.com');

        self::assertTrue($entry->matches(''));
        self::assertTrue($entry->matches('BIU'));
        self::assertTrue($entry->matches('example'));
        self::assertTrue($entry->matches('abcd'));
        self::assertFalse($entry->matches('dom'));
    }

    public function testNewIdentifiersAreEightHexadecimalCharacters(): void
    {
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', AddressEntry::newId());
        self::assertNotSame(AddressEntry::newId(), AddressEntry::newId());
    }
}
