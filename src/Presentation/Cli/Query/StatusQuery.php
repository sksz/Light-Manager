<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Presentation\Cli\LoopState;

/**
 * `core.status` — ostatnie zdanie, które aplikacja powiedziała, wraz z tonem.
 *
 * Zdanie jest **gotowym napisem**, a nie kluczem, i to jest jedyne miejsce
 * w spisie kwerend, gdzie tak jest — bo `Message` niesie treść już
 * przetłumaczoną i sparametryzowaną; klucza nikt nie zachowuje, więc nie ma
 * czego oddać. Ton jest za to nazwą stanu, nie napisem.
 *
 * Kwerenda **ulotna**: komunikat gaśnie sam po przeczytaniu (krok 19), więc
 * pokolenia nie ma jak zbudować z niczego trwałego.
 */
final class StatusQuery implements QueryInterface
{
    public function __construct(
        private readonly LoopState $state,
    ) {
    }

    public function name(): string
    {
        return 'core.status';
    }

    public function descriptionKey(): string
    {
        return 'query.core.status';
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
        $message = $this->state->message();

        if ($message === null) {
            return QueryResult::empty();
        }

        return QueryResult::of([[
            'text' => $message->text,
            'tone' => strtolower($message->tone->name),
        ]]);
    }
}
