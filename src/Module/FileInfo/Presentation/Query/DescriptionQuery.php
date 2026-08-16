<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\FileInfo\Application\Dto\EntryDescription;
use LightManager\Module\FileInfo\Application\FileInfoSettings;
use LightManager\Module\FileInfo\Presentation\FileInfoState;

/**
 * `file-info.description` — pełny obraz zaznaczonego wpisu: rodzaj, prawa,
 * czasy, rozmiar, cel dowiązania.
 *
 * Kwerenda, której **żaden inny moduł nie zastąpi**: `ModuleContext` niesie
 * o zaznaczeniu cztery pola, a tutaj jest wszystko, co `lstat` i `file` mają do
 * powiedzenia. To zarazem jedyna droga do tej wiedzy dla kogokolwiek spoza tego
 * modułu — czytanie `lstat` na własną rękę byłoby drugą implementacją opisu.
 *
 * Wiersze niosą **klucze** etykiet, a nie napisy, i to jest różnica wobec tego,
 * co widać na ekranie: ekran tłumaczy przez katalog, bo rysuje; moduł pytający
 * dostaje daną, więc dostaje klucz, który sam może przetłumaczyć albo zignorować.
 */
final class DescriptionQuery implements QueryInterface
{
    public function __construct(
        private readonly FileInfoState $state,
    ) {
    }

    public function name(): string
    {
        return FileInfoSettings::ID . '.description';
    }

    public function descriptionKey(): string
    {
        return 'module.' . FileInfoSettings::ID . '.query.description';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->state->revision();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $description = $this->state->description();

        if ($description === null) {
            return QueryResult::empty();
        }

        return QueryResult::owned(
            FileInfoSettings::ID,
            $description,
            static fn (): array => self::rowsOf($description),
        );
    }

    /** @return list<array<string, string|int|bool>> */
    private static function rowsOf(EntryDescription $description): array
    {
        $rows = [];

        foreach ($description->sections as $section) {
            foreach ($section->rows as $row) {
                $rows[] = [
                    'section' => $section->key,
                    'label' => $row->labelKey,
                    'value' => $row->value,
                ];
            }
        }

        return $rows;
    }
}
