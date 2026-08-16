<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Application\Undo\UndoJournal;

/**
 * `browser.undo` — stos operacji wraz z tym, które da się cofnąć.
 *
 * Stos mieszka w module (krok 44, D81: „jeden piszący, jeden czytający”)
 * i **tak zostaje** — kwerenda niczego stąd nie wyprowadza do rdzenia, oddaje
 * wyłącznie opis. Odwracalność jest przy tym daną, a nie napisem: rozstrzyga ją
 * `UndoEntry::reversible()`, więc pytający dowiaduje się tego samego, co widzi
 * użytkownik w oknie `F3`.
 *
 * Zapisu nie przeżywa zamknięcie aplikacji, więc pusty wynik przy starcie jest
 * stanem normalnym, a nie brakiem odpowiedzi.
 */
final class UndoQuery implements QueryInterface
{
    public function __construct(
        private readonly UndoJournal $journal,
    ) {
    }

    public function name(): string
    {
        return BrowserSettings::ID . '.undo';
    }

    public function descriptionKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.query.undo';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return self::VOLATILE;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $journal = $this->journal;

        return QueryResult::owned(BrowserSettings::ID, $journal, static function () use ($journal): array {
            $rows = [];

            foreach ($journal->entries() as $index => $entry) {
                $rows[] = [
                    'index' => $index,
                    'kind' => strtolower($entry->kind->name),
                    'directory' => $entry->directory,
                    'names' => count($entry->names),
                    'reversible' => $entry->reversible(),
                ];
            }

            return $rows;
        });
    }
}
