<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Query\Owner;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;

/**
 * `core.events` — zamknięty słownik momentów, które aplikacja ogłasza.
 *
 * Trzeci spis rdzenia obok komend i kwerend, a razem tworzą one całość zdania
 * z D85: **komenda robi, zdarzenie ogłasza, kwerenda mówi co wyszło**. Moduł
 * dopisujący odbiorcę zdarzeń miał dotąd jedno źródło nazw — kod tego, kto je
 * publikuje; odtąd ma je w danych.
 */
final class EventsQuery implements QueryInterface
{
    public function __construct(
        private readonly EventRegistry $events,
    ) {
    }

    public function name(): string
    {
        return 'core.events';
    }

    public function descriptionKey(): string
    {
        return 'query.core.events';
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
        $events = $this->events;

        return QueryResult::lazy(static function () use ($events): array {
            $rows = [];

            foreach ($events->all() as $declaration) {
                $rows[] = [
                    'name' => $declaration->name,
                    'owner' => Owner::of($declaration->name),
                    'label' => $declaration->labelKey,
                ];
            }

            return $rows;
        });
    }
}
