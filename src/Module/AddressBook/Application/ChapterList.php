<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

/**
 * Migawka wszystkich rozdziałów — ładunek kwerendy `address-book.chapters`
 * (krok 60).
 *
 * Spis obejmuje **rozdziały zadeklarowane i te obecne w danych**, w tej
 * kolejności; z niego biorą się zakładki ekranu książki.
 */
final readonly class ChapterList
{
    /** @param list<ChapterView> $chapters */
    public function __construct(public array $chapters = [])
    {
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_map(static fn (ChapterView $chapter): string => $chapter->id, $this->chapters);
    }

    public function at(int $index): ?ChapterView
    {
        return $this->chapters[$index] ?? null;
    }

    public function find(string $id): ?ChapterView
    {
        foreach ($this->chapters as $chapter) {
            if ($chapter->id === $id) {
                return $chapter;
            }
        }

        return null;
    }

    public function count(): int
    {
        return count($this->chapters);
    }
}
