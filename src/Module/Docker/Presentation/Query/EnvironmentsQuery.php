<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\EnvironmentRow;
use LightManager\Module\Docker\Application\Environments;
use LightManager\Module\Docker\Domain\ValueObject\EnvironmentKind;

/**
 * `docker.environments` — spis środowisk wraz z wyborem i stanem tunelu
 * (krok 58).
 *
 * **Poświadczenia nie wychodzą wierszami** (reguła 11w, kryterium kroku):
 * ścieżek kluczy TLS ani celu SSH tu nie ma — adresem wpisu tunelowego jest
 * gniazdo lokalne, czyli to, z czym moduł faktycznie rozmawia. Ta sama granica,
 * którą książka adresowa trzyma dla materiału uwierzytelnienia (krok 60).
 *
 * Pokolenie jest prawdziwym licznikiem: koordynator bije je przy każdej
 * zmianie książki, kontekstów, wyboru i stanu tunelu — czyli źródło umie
 * powiedzieć, że się zmieniło (warunek z D93 nr 1).
 */
final class EnvironmentsQuery implements QueryInterface
{
    public function __construct(
        private readonly Environments $environments,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.environments';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.query.environments';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->environments->revision();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $view = $this->environments->view();

        return QueryResult::owned(DockerSettings::ID, $view, static function () use ($view): array {
            $rows = [];

            foreach ($view->rows as $row) {
                $rows[] = self::describe($row, $view->tunnel->stage->value);
            }

            return $rows;
        });
    }

    /** @return array<string, string|int|bool> */
    private static function describe(EnvironmentRow $row, string $tunnelStage): array
    {
        return [
            'name' => $row->name,
            'kind' => $row->kind,
            'address' => $row->publicAddress,
            'origin' => $row->origin->value,
            'current' => $row->current,
            // Stan tunelu dotyczy wpisu, dla którego tunel prowadzi się teraz —
            // czyli bieżącego wpisu tunelowego; reszta wierszy milczy.
            'tunnel' => $row->current && $row->kind === EnvironmentKind::SshTunnel->value ? $tunnelStage : '',
        ];
    }
}
