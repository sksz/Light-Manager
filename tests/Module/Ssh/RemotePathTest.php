<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Ssh;

use LightManager\Module\Ssh\Domain\Exception\InvalidRemotePathException;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntryType;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteNameFilter;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;
use LightManager\Module\Ssh\Infrastructure\RemoteEntryComparator;
use PHPUnit\Framework\TestCase;

/**
 * Własna domena plikowa modułu sesji zdalnej (krok 49).
 *
 * Trzy pojęcia powtórzone świadomie wobec przeglądarki — ścieżka, wpis
 * i porządek — wraz z **granicą tego powtarzania** zapisaną w `SKILL.md`:
 * wolno powtórzyć pojęcia domeny, nie wolno powtórzyć mechanizmu rdzenia.
 *
 * Różnica, którą ten test pilnuje najmocniej: `RemotePath` porządkuje ścieżkę
 * **tekstowo**, bo systemu plików po drugiej stronie nie ma o co zapytać bez
 * obiegu do serwera.
 */
final class RemotePathTest extends TestCase
{
    public function testPathIsNormalisedTextually(): void
    {
        self::assertSame('/var/log', RemotePath::of('/var//log/')->value);
        self::assertSame('/var/log', RemotePath::of('/var/./log')->value);
        self::assertSame('/var', RemotePath::of('/var/log/..')->value);
    }

    /** `..` powyżej korzenia znika, zamiast wyprowadzać ścieżkę poza drzewo. */
    public function testGoingAboveTheRootStopsAtTheRoot(): void
    {
        self::assertSame('/', RemotePath::of('/../..')->value);
        self::assertTrue(RemotePath::root()->parent()->isRoot());
    }

    public function testChildAndParentAreOpposites(): void
    {
        $path = RemotePath::of('/home/anna');

        self::assertSame('/home/anna/dokumenty', $path->child('dokumenty')->value);
        self::assertSame('/home', $path->parent()->value);
        self::assertSame('anna', $path->name());
    }

    /** Nazwa z odstępem jest zwykłą nazwą — składanie ścieżki nic z nią nie robi. */
    public function testChildKeepsSpacesInNames(): void
    {
        self::assertSame('/home/kat ze spacja', RemotePath::of('/home')->child('kat ze spacja')->value);
    }

    public function testRelativePathIsRefused(): void
    {
        $this->expectException(InvalidRemotePathException::class);

        RemotePath::of('home/anna');
    }

    public function testEmptyPathIsRefused(): void
    {
        $this->expectException(InvalidRemotePathException::class);

        RemotePath::of('   ');
    }

    /** Korzeń doklejany do nazwy nie podwaja ukośnika. */
    public function testRootPrefixHasASingleSeparator(): void
    {
        self::assertSame('/etc', RemotePath::root()->child('etc')->value);
    }

    public function testDirectoriesComeFirstAndNamesFollowTheLanguage(): void
    {
        $sorted = RemoteEntryComparator::create()->sort([
            new RemoteEntry('żaba.txt', RemoteEntryType::File),
            new RemoteEntry('ananas.txt', RemoteEntryType::File),
            new RemoteEntry('łąka', RemoteEntryType::Directory),
            new RemoteEntry('Alfa', RemoteEntryType::Directory),
        ]);

        self::assertSame(
            ['Alfa', 'łąka', 'ananas.txt', 'żaba.txt'],
            array_map(static fn (RemoteEntry $entry): string => $entry->name, $sorted),
        );
    }

    /** Dowiązanie **nie jest katalogiem** i w porządku listy stoi z plikami. */
    public function testSymlinksSortWithFiles(): void
    {
        $sorted = RemoteEntryComparator::create()->sort([
            new RemoteEntry('link', RemoteEntryType::Symlink),
            new RemoteEntry('katalog', RemoteEntryType::Directory),
        ]);

        self::assertSame('katalog', $sorted[0]->name);
    }

    public function testFilterMatchesIgnoringCaseAndBeyondAscii(): void
    {
        $filter = new RemoteNameFilter('ŁĄ');

        self::assertTrue($filter->matches('łąka.txt'));
        self::assertFalse($filter->matches('laka.txt'));
        self::assertTrue(RemoteNameFilter::none()->matches('cokolwiek'));
    }

    /** Wpis zdalny odróżnia „nie wiem" od zera — jedno i drugie ma inne znaczenie. */
    public function testUnknownAttributesAreNullNotZero(): void
    {
        $entry = new RemoteEntry('plik.txt', RemoteEntryType::File);

        self::assertNull($entry->sizeInBytes);
        self::assertNull($entry->modifiedAt);
        self::assertSame('', $entry->permissionsAsText());
    }
}
