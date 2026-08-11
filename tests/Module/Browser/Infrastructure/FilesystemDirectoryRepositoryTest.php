<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Infrastructure;

use LightManager\Module\Browser\Domain\Exception\DirectoryNotReadableException;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Module\Browser\Infrastructure\EntryComparator;
use LightManager\Module\Browser\Infrastructure\FilesystemDirectoryRepository;
use PHPUnit\Framework\TestCase;

/**
 * Jedyny test w projekcie, który celowo dotyka dysku — repozytorium bez
 * prawdziwego katalogu nie sprawdziłoby niczego istotnego.
 */
final class FilesystemDirectoryRepositoryTest extends TestCase
{
    private string $root;

    private FilesystemDirectoryRepository $repository;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/light-manager-test-' . bin2hex(random_bytes(6));

        mkdir($this->root . '/podkatalog', 0o755, true);
        mkdir($this->root . '/.ukryty-katalog', 0o755, true);
        file_put_contents($this->root . '/notatka.txt', str_repeat('x', 42));
        file_put_contents($this->root . '/.ukryty-plik', 'x');

        $this->repository = new FilesystemDirectoryRepository(EntryComparator::create());
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    public function testReadsDirectoryWithoutHiddenEntries(): void
    {
        $directory = $this->repository->get(new DirectoryPath($this->root), false);

        self::assertSame(['podkatalog', 'notatka.txt'], $this->names($directory->entries()));
    }

    public function testReadsDirectoryWithHiddenEntries(): void
    {
        $directory = $this->repository->get(new DirectoryPath($this->root), true);

        self::assertSame(
            ['.ukryty-katalog', 'podkatalog', '.ukryty-plik', 'notatka.txt'],
            $this->names($directory->entries()),
        );
    }

    public function testRecognisesTypeAndSize(): void
    {
        $entries = $this->repository->get(new DirectoryPath($this->root), false)->entries();

        self::assertTrue($entries[0]->isDirectory());
        self::assertFalse($entries[1]->isDirectory());
        self::assertSame(42, $entries[1]->sizeInBytes);
    }

    public function testNeverIncludesDotEntries(): void
    {
        $names = $this->names($this->repository->get(new DirectoryPath($this->root), true)->entries());

        self::assertNotContains('.', $names);
        self::assertNotContains('..', $names);
    }

    public function testEmptyDirectoryHasNoEntriesAndNoSelection(): void
    {
        mkdir($this->root . '/pusty');

        $directory = $this->repository->get(new DirectoryPath($this->root . '/pusty'), false);

        self::assertTrue($directory->isEmpty());
        self::assertNull($directory->selection());
    }

    public function testMissingDirectoryIsReportedAsNotReadable(): void
    {
        $this->expectException(DirectoryNotReadableException::class);

        $this->repository->get(new DirectoryPath($this->root . '/nie-ma-takiego'), false);
    }

    public function testUnreadableDirectoryIsReportedWithoutWarnings(): void
    {
        if (posix_geteuid() === 0) {
            self::markTestSkipped('Uprawnienia nie ograniczają procesu roota.');
        }

        $forbidden = $this->root . '/bez-uprawnien';
        mkdir($forbidden, 0o000);

        try {
            $this->expectException(DirectoryNotReadableException::class);

            $this->repository->get(new DirectoryPath($forbidden), false);
        } finally {
            chmod($forbidden, 0o755);
        }
    }

    public function testBrokenSymlinkGetsZeroSizeInsteadOfFailing(): void
    {
        symlink($this->root . '/nie-ma-celu', $this->root . '/zerwane-dowiazanie');

        $entries = $this->repository->get(new DirectoryPath($this->root), false)->entries();
        $names = $this->names($entries);
        $index = array_search('zerwane-dowiazanie', $names, true);

        self::assertIsInt($index);
        self::assertSame(0, $entries[$index]->sizeInBytes);
    }

    /**
     * Czas i prawa doszły w kroku 27, bo lista pokazuje je w kolumnach.
     *
     * Prawa sprawdzamy przez `chmod` z wartością nietypową (`0o640`), a nie
     * domyślną — domyślne zależą od `umask` maszyny, na której test akurat biegnie.
     */
    public function testEntriesCarryTheirModificationTimeAndPermissions(): void
    {
        chmod($this->root . '/notatka.txt', 0o640);

        $entries = $this->repository->get(new DirectoryPath($this->root), false)->entries();

        self::assertSame(0o640, $entries[1]->permissions);
        self::assertSame('rw-r-----', $entries[1]->permissionsAsText());
        self::assertNotNull($entries[1]->modifiedAt);
        self::assertEqualsWithDelta(time(), $entries[1]->modifiedAt, 60);
    }

    /** Katalog też ma czas i prawa — kolumny nie robią wyjątku dla wpisu bez rozmiaru. */
    public function testDirectoriesCarryThemToo(): void
    {
        chmod($this->root . '/podkatalog', 0o750);

        $entries = $this->repository->get(new DirectoryPath($this->root), false)->entries();

        self::assertTrue($entries[0]->isDirectory());
        self::assertSame('rwxr-x---', $entries[0]->permissionsAsText());
        self::assertNotNull($entries[0]->modifiedAt);
    }

    /**
     * Wpis, o który nie da się zapytać, oddaje `null` — a nie zmyśloną datę.
     * Kolumna pokazuje wtedy pustkę i to jest właściwa odpowiedź.
     */
    public function testABrokenSymlinkHasNoTimeAndNoPermissions(): void
    {
        symlink($this->root . '/nie-ma-celu', $this->root . '/zerwane-dowiazanie');

        $entries = $this->repository->get(new DirectoryPath($this->root), false)->entries();
        $index = array_search('zerwane-dowiazanie', $this->names($entries), true);

        self::assertIsInt($index);
        self::assertNull($entries[$index]->modifiedAt);
        self::assertNull($entries[$index]->permissions);
        self::assertSame('', $entries[$index]->permissionsAsText());
    }

    /**
     * Dowiązanie do katalogu jest **katalogiem** — tak było przed krokiem 27
     * i tak ma zostać. Zamiana `is_dir()` na jedno `stat()` mogłaby to po cichu
     * odwrócić, gdyby ktoś sięgnął po `lstat()`.
     */
    public function testASymlinkToADirectoryStillCountsAsADirectory(): void
    {
        symlink($this->root . '/podkatalog', $this->root . '/skrot');

        $entries = $this->repository->get(new DirectoryPath($this->root), false)->entries();
        $index = array_search('skrot', $this->names($entries), true);

        self::assertIsInt($index);
        self::assertTrue($entries[$index]->isDirectory());
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

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $child = $path . '/' . $name;

            if (is_dir($child) && !is_link($child)) {
                $this->removeTree($child);
            } else {
                unlink($child);
            }
        }

        rmdir($path);
    }
}
