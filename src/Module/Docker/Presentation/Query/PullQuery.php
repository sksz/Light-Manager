<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\PullStage;
use LightManager\Module\Docker\Application\PullWork;

/**
 * `docker.pull` — stan pobierania obrazu (krok 61, etap 3).
 *
 * Bliźniak `docker.push` i istnieje z tego samego powodu: praca trwa dłużej niż
 * klatka, więc kwerenda **oddaje jej stan**, a nie czeka na koniec (11w).
 * `VOLATILE`, bo praca zmienia się co takt i pamiętanie odpowiedzi „na jedną
 * klatkę" oddawałoby stan sprzed zmiany, która padła w tej samej klatce.
 */
final class PullQuery implements QueryInterface
{
    public function __construct(
        private readonly PullWork $work,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.pull';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.query.pull';
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
        $target = $this->work->target();

        return QueryResult::of([[
            'stage' => match ($this->work->stage()) {
                PullStage::Idle => 'idle',
                PullStage::Pulling => 'pulling',
                PullStage::Done => 'done',
                PullStage::Failed => 'failed',
            },
            'image' => $target === null ? '' : $target->value,
            'note' => $this->work->note(),
            'problem' => $this->work->problemKey() ?? '',
        ]]);
    }
}
