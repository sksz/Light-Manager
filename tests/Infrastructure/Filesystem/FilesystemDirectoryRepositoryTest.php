<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Filesystem;

use LightManager\Domain\Exception\DirectoryNotReadableException;
use LightManager\Domain\ValueObject\DirectoryPath;
use LightManager\Domain\ValueObject\Entry;
use LightManager\Infrastructure\Filesystem\EntryComparator;
use LightManager\Infrastructure\Filesystem\FilesystemDirectoryRepository;
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
