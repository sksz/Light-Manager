<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\Port\ComposePort;
use LightManager\Module\Docker\Domain\ValueObject\ComposeProject;

/**
 * `docker.compose` — projekty compose wraz z etapem pracy nad nimi.
 *
 * Ulotna, i to jest tu jedyne uczciwe wyjście: praca compose żyje w procesie
 * potomnym, więc o zmianie dowiadujemy się dopiero z `advance()` w takcie —
 * a licznik bity tam biłby przy każdym takcie, czyli byłby ulotnością pod inną
 * nazwą. `ask()` przepisuje kilka pól gotowego obiektu stanu.
 *
 * Wiersze mówią o **projektach**, a etap — o pracy, która właśnie trwa. Przy
 * `compose up` trwającym minutami lista projektów jest pusta (wtyczka oddaje ją
 * dopiero z `ls`), więc pusta odpowiedź dostaje wiersz z samym etapem — tak samo,
 * jak w `ssh.entries` i z tego samego powodu: „nie ma projektów” i „właśnie je
 * podnoszę” nie mają prawa wyglądać identycznie.
 */
final class ComposeQuery implements QueryInterface
{
    public function __construct(
        private readonly ComposePort $compose,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.compose';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.query.compose';
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
        $state = $this->compose->state();

        return QueryResult::owned(DockerSettings::ID, $state, static function () use ($state): array {
            $stage = strtolower($state->stage->name);
            $rows = [];

            foreach ($state->projects as $project) {
                $rows[] = self::describe($project, $stage);
            }

            return $rows === [] ? [[
                'name' => '',
                'status' => '',
                'file' => '',
                'running' => false,
                'stage' => $stage,
                'action' => $state->action->value ?? '',
                'note' => $state->note,
                'problem' => $state->problemKey ?? '',
            ]] : $rows;
        });
    }

    /** @return array<string, string|int|bool> */
    private static function describe(ComposeProject $project, string $stage): array
    {
        return [
            'name' => $project->name,
            'status' => $project->status,
            'file' => $project->configPath ?? '',
            'running' => $project->isRunning(),
            'stage' => $stage,
            'action' => '',
            'note' => '',
            'problem' => '',
        ];
    }
}
