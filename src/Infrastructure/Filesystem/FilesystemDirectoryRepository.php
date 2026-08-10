<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Filesystem;

use LightManager\Domain\Aggregate\Directory;
use LightManager\Domain\Exception\DirectoryNotReadableException;
use LightManager\Domain\Repository\DirectoryRepositoryInterface;
use LightManager\Domain\ValueObject\DirectoryPath;
use LightManager\Domain\ValueObject\Entry;

final class FilesystemDirectoryRepository implements DirectoryRepositoryInterface
{
    public function __construct(
        private readonly EntryComparator $comparator,
    ) {
    }

    public function get(DirectoryPath $path, bool $includeHidden): Directory
    {
        // Wyciszone ostrzeżenia: katalog może zniknąć albo stracić uprawnienia
        // między jednym a drugim wywołaniem, a komunikat PHP trafiłby wprost na
        // rysowaną klatkę. Interesuje nas wyłącznie fakt niepowodzenia.
        $names = @scandir($path->value);

        if ($names === false) {
            throw DirectoryNotReadableException::forPath($path);
        }

        $entries = [];

        foreach ($names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            if (!$includeHidden && str_starts_with($name, '.')) {
                continue;
            }

            $entries[] = $this->toEntry($path, $name);
        }

        return new Directory($path, $this->comparator->sort($entries));
    }

    private function toEntry(DirectoryPath $path, string $name): Entry
    {
        $fullPath = $path->child($name)->value;

        if (is_dir($fullPath)) {
            return Entry::directory($name);
        }

        $size = @filesize($fullPath);

        // Zerwane dowiązanie albo plik zniknięty w międzyczasie — rozmiar 0 jest
        // lepszy niż wywrócenie się całego odczytu katalogu.
        return Entry::file($name, $size === false ? 0 : $size);
    }
}
