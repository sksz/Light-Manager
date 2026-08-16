<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\Owner;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Application\Query\QueryResult;

/**
 * `core.queries` — spis wszystkich źródeł danych tego uruchomienia.
 *
 * Samoopis rejestru i **źródło listy w oknie kwerend**: okno nie prowadzi
 * drugiego spisu, tylko pokazuje ten. Dzięki temu kwerenda modułu wyłączonego
 * nie stoi w oknie z tego samego powodu, dla którego nie stoi w rejestrze —
 * a nie dlatego, że ktoś pamiętał ją odfiltrować.
 *
 * Kwerenda pytająca rejestr, w którym sama stoi, **nie jest kwerendą wołającą
 * kwerendę**: czyta spis, a nie odpowiedzi. Strażnik z `ask()` łapie dopiero to
 * drugie.
 */
final class QueriesQuery implements QueryInterface
{
    public function __construct(
        private readonly QueryRegistry $queries,
    ) {
    }

    public function name(): string
    {
        return 'core.queries';
    }

    public function descriptionKey(): string
    {
        return 'query.core.queries';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return 0;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $queries = $this->queries;

        return QueryResult::lazy(static function () use ($queries): array {
            $rows = [];

            foreach ($queries->all() as $query) {
                $rows[] = [
                    'name' => $query->name(),
                    'owner' => Owner::of($query->name()),
                    'description' => $query->descriptionKey(),
                    'arguments' => count($query->arguments()),
                ];
            }

            return $rows;
        });
    }
}
