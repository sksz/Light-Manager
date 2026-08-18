<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\AddressBook;

use LightManager\Module\AddressBook\Application\AddressBook;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use PHPUnit\Framework\TestCase;

/**
 * Książka adresowa: porządek, tożsamość i szukanie (krok 60).
 */
final class AddressBookTest extends TestCase
{
    public function testEntriesKeepTheOrderTheyWereAddedIn(): void
    {
        $book = new AddressBook([
            new AddressEntry('0000000c', 'zapasowy', 'backup.example.com'),
            new AddressEntry('0000000a', 'biuro', 'example.com'),
        ]);

        self::assertSame(['zapasowy', 'biuro'], array_map(
            static fn (AddressEntry $entry): string => $entry->name,
            $book->sorted(false),
        ));
    }

    /** Alfabet idzie po tym, **co widać** — więc wpis bez nazwy sortuje się identyfikatorem. */
    public function testAlphabeticalOrderUsesWhatIsOnTheScreen(): void
    {
        $book = new AddressBook([
            new AddressEntry('0000000c', 'zapasowy', 'backup.example.com'),
            new AddressEntry('0000000a', '', 'example.com'),
            new AddressEntry('0000000b', 'biuro', 'example.com'),
        ]);

        self::assertSame(['0000000a', 'biuro', 'zapasowy'], array_map(
            static fn (AddressEntry $entry): string => $entry->label(),
            $book->sorted(true),
        ));
    }

    /** Dopisanie pod zajętym identyfikatorem **zastępuje wpis, zachowując jego miejsce**. */
    public function testPuttingUnderATakenIdentifierReplacesInPlace(): void
    {
        $book = new AddressBook([
            new AddressEntry('0000000a', 'biuro', 'example.com'),
            new AddressEntry('0000000b', 'dom', '192.168.1.10'),
        ]);

        $book->put(new AddressEntry('0000000a', 'biuro główne', '10.0.0.5'));

        self::assertSame(2, $book->count());
        self::assertSame(['0000000a', '0000000b'], $book->ids());
        self::assertSame('biuro główne', $book->find('0000000a')?->name);
    }

    /**
     * **Nazwa powtórzona daje odpowiedź „nie wiem"**, a nie pierwsze trafienie.
     *
     * Droga po nazwie istnieje wyłącznie dla zgodności wstecz (wpisy zapisane
     * u obcych przed krokiem 60), więc zgadywanie, o który z dwóch wpisów
     * chodzi, byłoby gorsze od milczenia.
     */
    public function testAnAmbiguousNameResolvesToNothing(): void
    {
        $book = new AddressBook([
            new AddressEntry('0000000a', 'biuro', 'example.com'),
            new AddressEntry('0000000b', 'biuro', '10.0.0.5'),
            new AddressEntry('0000000c', 'dom', '192.168.1.10'),
        ]);

        self::assertNull($book->findByName('biuro'));
        self::assertSame('0000000c', $book->findByName('dom')?->id);
        self::assertNull($book->findByName(''), 'pusta nazwa nie wskazuje niczego');
    }

    public function testRemovingSaysWhetherThereWasAnything(): void
    {
        $book = new AddressBook([new AddressEntry('0000000a', 'biuro', 'example.com')]);

        self::assertTrue($book->remove('0000000a'));
        self::assertFalse($book->remove('0000000a'));
        self::assertTrue($book->isEmpty());
    }

    /** Nowy identyfikator jest wolny — książka jest jedynym miejscem widzącym zajęte. */
    public function testNextIdentifierIsFree(): void
    {
        $book = new AddressBook([new AddressEntry('0000000a', 'biuro', 'example.com')]);

        $id = $book->nextId();

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}$/', $id);
        self::assertNull($book->find($id));
    }
}
