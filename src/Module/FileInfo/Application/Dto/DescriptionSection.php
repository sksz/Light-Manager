<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Dto;

/**
 * Grupa wierszy opisu wraz z kluczem i etykietą.
 *
 * Klucz jest **napisem, a nie numerem**, i to nie jest szczegół: stan zwinięcia
 * sekcji (`SectionState`, krok 22) pamięta się właśnie pod nim, więc sekcja,
 * która zniknęła — bo katalog nie ma sumy kontrolnej — i wróciła przy innym
 * pliku, wraca w tym samym stanie.
 */
final class DescriptionSection
{
    /** @param list<DescriptionRow> $rows */
    public function __construct(
        public readonly string $key,
        public readonly string $labelKey,
        public readonly array $rows,
    ) {
    }

    /** @param list<DescriptionRow> $rows */
    public function withRows(array $rows): self
    {
        return new self($this->key, $this->labelKey, $rows);
    }
}
