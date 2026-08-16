<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Infrastructure\Process\BackgroundProcessService;

/**
 * `core.jobs` — prace tłowe tego uruchomienia: co chodzi, co się skończyło
 * i z jakim kodem.
 *
 * **Treści wypisu tu nie ma i nie będzie.** Log kontenera, listing zdalnego
 * katalogu i suma `du` należą do tych, którzy je zamówili; spis prac odpowiada
 * na inne pytanie — *czy coś obok trwa i czy się udało*. Rozmiar wypisu wchodzi,
 * bo mówi „coś tam jest” bez pokazywania czego.
 *
 * Pokolenie jest **ulotne**: etap i liczba bajtów zmieniają się w każdym takcie,
 * więc nie ma numeru, który dałoby się porównać — rejestr przelicza tę kwerendę
 * najwyżej raz na klatkę i to jest właściwa cena.
 */
final class JobsQuery implements QueryInterface
{
    public function __construct(
        private readonly BackgroundProcessService $jobs,
    ) {
    }

    public function name(): string
    {
        return 'core.jobs';
    }

    public function descriptionKey(): string
    {
        return 'query.core.jobs';
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
        $states = $this->jobs->states();

        return QueryResult::lazy(static function () use ($states): array {
            $rows = [];

            foreach ($states as $id => $state) {
                $rows[] = [
                    'handle' => $id,
                    'stage' => strtolower($state->stage->name),
                    'exit' => $state->exitCode ?? -1,
                    'bytes' => strlen($state->output),
                    'dropped' => $state->droppedBytes,
                    'problem' => $state->problemKey ?? '',
                ];
            }

            return $rows;
        });
    }
}
