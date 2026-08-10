<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Domain\Aggregate\Directory;
use LightManager\Domain\Exception\DirectoryNotReadableException;
use LightManager\Domain\Repository\DirectoryRepositoryInterface;
use LightManager\Domain\ValueObject\DirectoryPath;
use LightManager\Domain\ValueObject\Entry;

/**
 * Drzewo katalogów trzymane w pamięci — pozwala testować nawigację bez
 * dotykania systemu plików.
 */
final class InMemoryDirectoryRepository implements DirectoryRepositoryInterface
{
    /** @var array<string, list<Entry>> */
    private array $tree = [];

    /** @var list<string> */
    private array $unreadable = [];

    public int $reads = 0;

    /** @param list<Entry> $entries */
    public function add(string $path, array $entries): self
    {
        $this->tree[$path] = $entries;

        return $this;
    }

    public function makeUnreadable(string $path): self
    {
        $this->unreadable[] = $path;

        return $this;
    }

    public function get(DirectoryPath $path, bool $includeHidden): Directory
    {
        ++$this->reads;

        if (in_array($path->value, $this->unreadable, true) || !isset($this->tree[$path->value])) {
            throw DirectoryNotReadableException::forPath($path);
        }

        $entries = $this->tree[$path->value];

        if (!$includeHidden) {
            $entries = array_values(array_filter($entries, static fn (Entry $e): bool => !$e->isHidden()));
        }

        return new Directory($path, $entries);
    }
}
