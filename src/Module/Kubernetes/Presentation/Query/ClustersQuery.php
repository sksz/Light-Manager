<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Kubernetes\Application\Clusters;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;

/**
 * `k8s.clusters` — spis klastrów z obu źródeł (krok 59).
 *
 * **Ścieżka pliku wychodzi wierszami** i jest to rozstrzygnięcie planu (punkt
 * 8): nie jest materiałem uwierzytelnienia, tylko lokalizacją pliku, którą
 * użytkownik sam wpisał. Adres serwera nadal **nie wychodzi** (reguła 11w) —
 * granica postawiona w kroku 54 zostaje nietknięta.
 *
 * Pokolenie bierze się z licznika koordynatora, a nie z `VOLATILE`: spis zmienia
 * się wyłącznie wtedy, gdy ktoś dopisze wpis, wybierze inny albo skończy się
 * odczyt pliku — a każde z tych zdarzeń licznik podbija.
 */
final class ClustersQuery implements QueryInterface
{
    public function __construct(
        private readonly Clusters $clusters,
    ) {
    }

    public function name(): string
    {
        return KubernetesSettings::ID . '.clusters';
    }

    public function descriptionKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.query.clusters';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->clusters->revision();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $view = $this->clusters->view();

        return QueryResult::owned(KubernetesSettings::ID, $view, static function () use ($view): array {
            $rows = [];

            foreach ($view->rows as $row) {
                $rows[] = [
                    'name' => $row->name,
                    'kubeconfig' => $row->kubeconfig,
                    'context' => $row->context,
                    'namespace' => $row->namespace,
                    'origin' => $row->origin->value,
                    'current' => $row->current,
                    'shadowed' => $row->shadowed,
                ];
            }

            if ($rows === []) {
                // Pusta lista dostaje wiersz z samym etapem — inaczej „czytam",
                // „nie ma nic" i „nikt jeszcze nie pytał" wyglądają dla obcego
                // identycznie (reguła 11w, lekcja kroku 54).
                $rows[] = ['name' => '', 'reading' => $view->reading, 'problem' => $view->problemKey ?? ''];
            }

            return $rows;
        });
    }
}
