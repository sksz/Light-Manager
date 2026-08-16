<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Query\Owner;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;

/**
 * `core.commands` — spis czynności, które da się wywołać po nazwie.
 *
 * Druga połowa zdania „komenda robi, kwerenda mówi”: moduł, który chce cudzej
 * **czynności**, bierze ją z rejestru komend — a żeby wiedzieć, czy tamten
 * moduł ją wnosi, pyta stąd. Do tego kroku spis widziało wyłącznie okno komend.
 */
final class CommandsQuery implements QueryInterface
{
    public function __construct(
        private readonly CommandRegistry $commands,
    ) {
    }

    public function name(): string
    {
        return 'core.commands';
    }

    public function descriptionKey(): string
    {
        return 'query.core.commands';
    }

    public function arguments(): array
    {
        return [];
    }

    /** Rejestr komend powstaje raz, przy składaniu aplikacji. */
    public function generation(): int
    {
        return 0;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $commands = $this->commands;

        return QueryResult::lazy(static function () use ($commands): array {
            $rows = [];

            foreach ($commands->all() as $command) {
                $rows[] = [
                    'name' => $command->name(),
                    'owner' => Owner::of($command->name()),
                    'description' => $command->descriptionKey(),
                    'arguments' => count($command->arguments()),
                ];
            }

            return $rows;
        });
    }
}
