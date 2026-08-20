<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\Registry\RegistryMode;
use LightManager\Module\Docker\Application\RegistryBrowse;

/**
 * `docker.catalog` — zawartość oglądanego rejestru (krok 61, etap 2).
 *
 * **Etap stoi w każdym wierszu, a spis pusty dostaje wiersz z samym etapem** —
 * reguła 11w, dopisana w kroku 54 wraz z `ssh.entries` i `k8s.resources`:
 * inaczej „czytam", „nie ma nic" i „nikt jeszcze nie pytał" wyglądają dla obcego
 * identycznie. Przy rozmowie sieciowej o **trzech obiegach** ma to wagę większą
 * niż gdzie indziej: „czekam" trwa tu zauważalnie i bywa trzema różnymi
 * czekaniami.
 */
final class CatalogQuery implements QueryInterface
{
    public function __construct(
        private readonly RegistryBrowse $browse,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.catalog';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.query.catalog';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->browse->revision();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $view = $this->browse->view();

        return QueryResult::owned(DockerSettings::ID, $view, static function () use ($view): array {
            $kind = match ($view->mode) {
                RegistryMode::Catalog => 'repository',
                RegistryMode::Tags => 'tag',
                RegistryMode::NeedsName => 'none',
            };

            if ($view->isEmpty()) {
                return [[
                    'stage' => $view->stage->value,
                    'registry' => $view->registry,
                    'kind' => $kind,
                    'name' => '',
                    'problem' => $view->problemKey ?? '',
                ]];
            }

            $rows = [];

            foreach ($view->rows as $name) {
                $rows[] = [
                    'stage' => $view->stage->value,
                    'registry' => $view->registry,
                    'kind' => $kind,
                    'name' => $name,
                    'problem' => '',
                ];
            }

            return $rows;
        });
    }
}
