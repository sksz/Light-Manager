<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Domain\ValueObject;

use LightManager\Module\Browser\Domain\Exception\InvalidEntryNameException;
use LightManager\Module\Browser\Domain\ValueObject\EntryName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Nazwa wpisana przez użytkownika (krok 41): sprawdzenie stoi w module, bo port
 * operacji bierze napisy i ufa wołającemu (D75, rozstrzygnięcie 2).
 *
 * Najważniejsze jest to, że **każda odmowa ma własny powód** — użytkownik, który
 * wpisał ukośnik, ma zobaczyć zdanie o ukośniku, a nie ogólne „zła nazwa”.
 */
final class EntryNameTest extends TestCase
{
    public function testAcceptsAnOrdinaryName(): void
    {
        self::assertSame('raport.txt', (new EntryName('raport.txt'))->value);
    }

    /** Nazwa zaczynająca się od kropki jest ukryta, ale poprawna. */
    public function testAcceptsAHiddenName(): void
    {
        self::assertSame('.szkic', (new EntryName('.szkic'))->value);
    }

    /**
     * Odstępów **nie obcinamy**: w systemach uniksowych są poprawne, a obcięcie
     * w milczeniu utworzyłoby coś innego, niż użytkownik napisał.
     */
    public function testKeepsSurroundingSpaces(): void
    {
        self::assertSame(' raport ', (new EntryName(' raport '))->value);
    }

    #[DataProvider('refusals')]
    public function testRefusesWithItsOwnReason(string $value, string $expectedKey): void
    {
        try {
            new EntryName($value);
        } catch (InvalidEntryNameException $problem) {
            self::assertSame($expectedKey, $problem->problemKey());

            return;
        }

        self::fail('nazwa „' . $value . '” miała zostać odrzucona');
    }

    /** @return iterable<string, array{string, string}> */
    public static function refusals(): iterable
    {
        yield 'pusta' => ['', 'module.browser.name.empty'];
        yield 'kropka' => ['.', 'module.browser.name.reserved'];
        yield 'dwie kropki' => ['..', 'module.browser.name.reserved'];
        yield 'ukośnik' => ['a/b', 'module.browser.name.separator'];
        yield 'sam ukośnik' => ['/', 'module.browser.name.separator'];
        yield 'bajt zerowy' => ["a\0b", 'module.browser.name.separator'];
        yield 'za długa' => [str_repeat('a', EntryName::MAX_BYTES + 1), 'module.browser.name.tooLong'];
    }

    /** Granica liczy się w **bajtach**, nie w znakach — tak jak w systemie plików. */
    public function testTheLimitCountsBytesNotCharacters(): void
    {
        // Sto trzydzieści znaków „ą” to 260 bajtów, więc nazwa jest za długa,
        // choć znaków ma mniej niż limit.
        $this->expectException(InvalidEntryNameException::class);

        new EntryName(str_repeat('ą', 130));
    }

    public function testEqualityComparesTheValue(): void
    {
        self::assertTrue((new EntryName('a'))->equals(new EntryName('a')));
        self::assertFalse((new EntryName('a'))->equals(new EntryName('b')));
    }
}
