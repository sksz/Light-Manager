<?php

declare(strict_types=1);

namespace LightManager\Domain\Aggregate;

use LightManager\Domain\Exception\InvalidSelectionException;
use LightManager\Domain\ValueObject\DirectoryPath;
use LightManager\Domain\ValueObject\Entry;
use LightManager\Domain\ValueObject\Selection;

/**
 * Korzeń agregatu: katalog wraz z zawartością i bieżącym zaznaczeniem.
 *
 * W odróżnieniu od obiektów wartości jest **mutowalny w miejscu** — zmiana
 * zaznaczenia nie tworzy nowej instancji. Pilnuje niezmiennika: zaznaczenie
 * wskazuje istniejący wpis albo nie ma go wcale, gdy katalog jest pusty.
 */
final class Directory
{
    /** @var list<Entry> */
    private array $entries;

    private ?Selection $selection;

    /** @param list<Entry> $entries */
    public function __construct(
        private readonly DirectoryPath $path,
        array $entries,
        ?Selection $selection = null,
    ) {
        $this->entries = $entries;
        $this->selection = $this->validated($selection);
    }

    public function path(): DirectoryPath
    {
        return $this->path;
    }

    /** @return list<Entry> */
    public function entries(): array
    {
        return $this->entries;
    }

    public function isEmpty(): bool
    {
        return $this->entries === [];
    }

    public function selection(): ?Selection
    {
        return $this->selection;
    }

    public function selectedEntry(): ?Entry
    {
        return $this->selection === null ? null : $this->entries[$this->selection->index];
    }

    /** Zatrzymuje się na krańcu listy zamiast zawijać na drugi koniec. */
    public function moveSelectionUp(): void
    {
        if ($this->selection === null || $this->selection->index === 0) {
            return;
        }

        $this->selection = new Selection($this->selection->index - 1);
    }

    public function moveSelectionDown(): void
    {
        if ($this->selection === null || $this->selection->index === count($this->entries) - 1) {
            return;
        }

        $this->selection = new Selection($this->selection->index + 1);
    }

    /**
     * Ustawia zaznaczenie na wpisie o podanej nazwie. Gdy takiego wpisu nie ma
     * (np. zniknął po ukryciu wpisów ukrytych) — wraca na początek listy.
     */
    public function selectEntryNamed(string $name): void
    {
        foreach ($this->entries as $index => $entry) {
            if ($entry->name === $name) {
                $this->selection = new Selection($index);

                return;
            }
        }

        $this->selection = $this->entries === [] ? null : new Selection(0);
    }

    /** Tożsamość agregatu to jego ścieżka — zawartość się nie liczy. */
    public function equals(self $other): bool
    {
        return $this->path->equals($other->path);
    }

    private function validated(?Selection $selection): ?Selection
    {
        if ($this->entries === []) {
            return null;
        }

        if ($selection === null) {
            return new Selection(0);
        }

        if ($selection->index >= count($this->entries)) {
            throw InvalidSelectionException::outOfRange($selection->index, count($this->entries));
        }

        return $selection;
    }
}
