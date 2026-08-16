<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Kubernetes\Application\ClusterState;
use LightManager\Module\Kubernetes\Application\ClusterView;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;

/**
 * `k8s.contexts` — konteksty z `kubeconfig` wraz z tym, który jest bieżący.
 *
 * Ulotna, bo spis przychodzi z procesu potomnego i o zmianie dowiadujemy się
 * dopiero z `advance()` w takcie — licznik bity tam byłby ulotnością pod inną
 * nazwą. `ask()` składa migawkę z gotowych pól.
 *
 * **Nie pyta klastra o nic** i to jest tu reguła nadrzędna Fazy XVII w wydaniu
 * kroku 52: żadne wywołanie `kubectl` nie pada w rysowaniu klatki, bo żadne nie
 * pada w procesie aplikacji. Kwerenda oddaje to, co zastała.
 */
final class ContextsQuery implements QueryInterface
{
    public function __construct(
        private readonly ClusterState $cluster,
    ) {
    }

    public function name(): string
    {
        return KubernetesSettings::ID . '.contexts';
    }

    public function descriptionKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.query.contexts';
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
        $view = ClusterView::of($this->cluster);

        return QueryResult::owned(KubernetesSettings::ID, $view, static function () use ($view): array {
            $rows = [];
            $current = $view->current?->value;

            foreach ($view->contexts as $context) {
                $rows[] = [
                    'name' => $context->value,
                    'current' => $context->value === $current,
                ];
            }

            return $rows;
        });
    }
}
