<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Docker\Application\ContainerList;
use LightManager\Module\Docker\Application\ContainerView;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\Environments;
use LightManager\Module\Docker\Domain\ValueObject\Container;

/**
 * `docker.containers` — kontenery wraz z projektem compose, do którego należą.
 *
 * Wiersze niosą **zawężenie tak, jak je widać**: gdy panel stoi na jednym
 * projekcie, kwerenda oddaje ten projekt, a nie wszystko, co demon zna. To ta
 * sama zasada, którą `browser.entries` stosuje do filtra — kwerenda odpowiada
 * o tym, co pokazuje panel, bo odpowiedź „wszystko, co jest” byłaby odpowiedzią
 * na inne pytanie.
 */
final class ContainersQuery implements QueryInterface
{
    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly ContainerList $containers,
        private readonly Environments $environments,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.containers';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.query.containers';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->containers->revision();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $view = new ContainerView(
            $this->containers->entries(),
            $this->containers->cursor(),
            $this->containers->isLoaded(),
            $this->containers->project(),
            $this->containers->projects(),
            $this->containers->problemKey(),
        );

        // Nazwa środowiska w każdym wierszu (krok 58, reguła 11w): bez niej
        // odpowiedź dwóch różnych demonów wygląda dla obcego identycznie.
        $environment = $this->environments->currentName();

        return QueryResult::owned(DockerSettings::ID, $view, static function () use ($view, $environment): array {
            $rows = [];
            $selected = $view->selected();

            foreach ($view->entries as $container) {
                $rows[] = self::describe($container, $selected !== null && $selected->equals($container), $environment);
            }

            return $rows;
        });
    }

    /** @return array<string, string|int|bool> */
    private static function describe(Container $container, bool $selected, string $environment): array
    {
        return [
            'id' => $container->id->short(),
            'name' => $container->name,
            'image' => $container->image->value,
            'state' => strtolower($container->state->name),
            'status' => $container->status,
            'project' => $container->composeProject ?? '',
            'ports' => implode(' ', $container->ports),
            'created' => $container->createdAt === null ? '' : date(self::TIMESTAMP_FORMAT, $container->createdAt),
            'running' => $container->isRunning(),
            'selected' => $selected,
            'environment' => $environment,
        ];
    }
}
