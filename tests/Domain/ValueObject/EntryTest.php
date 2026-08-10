<?php

declare(strict_types=1);

namespace LightManager\Tests\Domain\ValueObject;

use LightManager\Domain\Exception\InvalidEntryException;
use LightManager\Domain\ValueObject\Entry;
use LightManager\Domain\ValueObject\EntryType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EntryTest extends TestCase
{
    public function testDescribesDirectory(): void
    {
        $entry = Entry::directory('dokumenty');

        self::assertSame(EntryType::Directory, $entry->type);
        self::assertTrue($entry->isDirectory());
        self::assertSame(0, $entry->sizeInBytes);
    }

    public function testDescribesFile(): void
    {
        $entry = Entry::file('notatka.txt', 1024);

        self::assertSame(EntryType::File, $entry->type);
        self::assertFalse($entry->isDirectory());
        self::assertSame(1024, $entry->sizeInBytes);
    }

    /** @return array<string, array{string, bool}> */
    public static function names(): array
    {
        return [
            'zwykły plik' => ['notatka.txt', false],
            'plik z kropką w środku' => ['archiwum.tar.gz', false],
            'ukryty plik' => ['.bashrc', true],
            'ukryty katalog' => ['.config', true],
        ];
    }

    #[DataProvider('names')]
    public function testRecognisesHiddenEntries(string $name, bool $hidden): void
    {
        self::assertSame($hidden, Entry::file($name, 0)->isHidden());
    }

    /** @return array<string, array{string}> */
    public static function invalidNames(): array
    {
        return [
            'pusta nazwa' => [''],
            'kropka' => ['.'],
            'dwie kropki' => ['..'],
            'nazwa ze ścieżką' => ['katalog/plik.txt'],
        ];
    }

    #[DataProvider('invalidNames')]
    public function testRejectsInvalidName(string $name): void
    {
        $this->expectException(InvalidEntryException::class);

        Entry::file($name, 0);
    }

    public function testRejectsNegativeSize(): void
    {
        $this->expectException(InvalidEntryException::class);

        Entry::file('notatka.txt', -1);
    }

    public function testComparesByValue(): void
    {
        $entry = Entry::file('notatka.txt', 10);

        self::assertTrue($entry->equals(Entry::file('notatka.txt', 10)));
        self::assertFalse($entry->equals(Entry::file('notatka.txt', 11)));
        self::assertFalse($entry->equals(Entry::directory('notatka.txt')));
    }
}
