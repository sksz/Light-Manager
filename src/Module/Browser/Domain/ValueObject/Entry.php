<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Domain\ValueObject;

use LightManager\Module\Browser\Domain\Exception\InvalidEntryException;

/**
 * Pojedynczy element katalogu. Nie ma własnej tożsamości — przy każdym odczycie
 * katalogu powstaje od nowa.
 */
final class Entry
{
    public function __construct(
        public readonly string $name,
        public readonly EntryType $type,
        public readonly int $sizeInBytes,
    ) {
        if ($name === '' || $name === '.' || $name === '..' || str_contains($name, '/')) {
            throw InvalidEntryException::forName($name);
        }

        if ($sizeInBytes < 0) {
            throw InvalidEntryException::forNegativeSize($name, $sizeInBytes);
        }
    }

    public static function directory(string $name): self
    {
        return new self($name, EntryType::Directory, 0);
    }

    public static function file(string $name, int $sizeInBytes): self
    {
        return new self($name, EntryType::File, $sizeInBytes);
    }

    public function isDirectory(): bool
    {
        return $this->type === EntryType::Directory;
    }

    /** Uniksowa konwencja: nazwa zaczynająca się od kropki jest ukryta. */
    public function isHidden(): bool
    {
        return str_starts_with($this->name, '.');
    }

    public function equals(self $other): bool
    {
        return $this->name === $other->name
            && $this->type === $other->type
            && $this->sizeInBytes === $other->sizeInBytes;
    }
}
