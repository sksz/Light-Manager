<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Infrastructure;

use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Infrastructure\EntryComparator;
use PHPUnit\Framework\TestCase;

final class EntryComparatorTest extends TestCase
{
    public function testPutsDirectoriesBeforeFiles(): void
    {
        $sorted = $this->fallbackComparator()->sort([
            Entry::file('aaa.txt', 0),
            Entry::directory('zzz'),
            Entry::file('bbb.txt', 0),
            Entry::directory('mmm'),
        ]);

        self::assertSame(['mmm', 'zzz', 'aaa.txt', 'bbb.txt'], $this->names($sorted));
    }

    public function testIgnoresLetterCase(): void
    {
        $sorted = $this->fallbackComparator()->sort([
            Entry::file('Zebra', 0),
            Entry::file('ala', 0),
            Entry::file('Bor', 0),
        ]);

        self::assertSame(['ala', 'Bor', 'Zebra'], $this->names($sorted));
    }

    public function testKeepsPolishLettersNextToTheirBaseLetters(): void
    {
        $sorted = $this->fallbackComparator()->sort([
            Entry::file('zebra', 0),
            Entry::file('ćma', 0),
            Entry::file('abc', 0),
            Entry::file('łan', 0),
        ]);

        self::assertSame(['abc', 'ćma', 'łan', 'zebra'], $this->names($sorted));
    }

    public function testCollatorPathSortsTheSameWay(): void
    {
        if (!extension_loaded('intl')) {
            self::markTestSkipped('Rozszerzenie intl nie jest dostępne.');
        }

        $sorted = EntryComparator::create()->sort([
            Entry::file('zebra', 0),
            Entry::file('ćma', 0),
            Entry::file('Abc', 0),
            Entry::file('łan', 0),
            Entry::directory('Katalog'),
        ]);

        self::assertSame(['Katalog', 'Abc', 'ćma', 'łan', 'zebra'], $this->names($sorted));
    }

    public function testEmptyListStaysEmpty(): void
    {
        self::assertSame([], $this->fallbackComparator()->sort([]));
    }

    /** Ścieżka awaryjna — bez `Collator`, tak jak na systemie bez rozszerzenia intl. */
    private function fallbackComparator(): EntryComparator
    {
        return new EntryComparator(null);
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<string>
     */
    private function names(array $entries): array
    {
        return array_map(static fn (Entry $entry): string => $entry->name, $entries);
    }
}
