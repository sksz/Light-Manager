<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\State;

use LightManager\Application\State\Book;
use PHPUnit\Framework\TestCase;

/**
 * Rdzeniowa książka wpisów (krok 59, D103) — dwie gwarancje i nic ponad nie.
 *
 * Test pilnuje dokładnie tego, co rdzeń obiecuje trzem modułowym książkom:
 * **nazwa jest tożsamością** (dopisanie pod zajętą nazwą zastępuje wpis,
 * zachowując miejsce) i **kolejność jest kolejnością dopisywania**. Ładunek
 * jest nieprzezroczysty, więc testuje się go na napisach — typy wozi moduł.
 */
final class BookTest extends TestCase
{
    public function testEntriesKeepTheOrderTheyWereAddedIn(): void
    {
        $book = new Book();
        $book->put('biuro', 'a');
        $book->put('dom', 'b');
        $book->put('chmura', 'c');

        self::assertSame(['biuro', 'dom', 'chmura'], $book->names());
        self::assertSame(3, $book->count());
    }

    public function testReplacingAnEntryKeepsItsPlace(): void
    {
        $book = new Book();
        $book->put('biuro', 'stary');
        $book->put('dom', 'b');
        $book->put('biuro', 'nowy');

        self::assertSame(['biuro', 'dom'], $book->names());
        self::assertSame('nowy', $book->find('biuro'));
    }

    public function testRemovingSaysWhetherAnythingWasThere(): void
    {
        $book = new Book();
        $book->put('biuro', 'a');

        self::assertTrue($book->remove('biuro'));
        self::assertFalse($book->remove('biuro'));
        self::assertSame([], $book->names());
    }

    /** Wpis bez tożsamości nie jest wpisem, a `null` jest odpowiedzią „nie ma". */
    public function testAnEmptyNameAndANullPayloadFallOut(): void
    {
        $book = new Book();
        $book->put('', 'bez nazwy');
        $book->put('bez ładunku', null);

        self::assertSame(0, $book->count());
        self::assertFalse($book->has('bez ładunku'));
        self::assertNull($book->find('nieobecny'));
    }
}
