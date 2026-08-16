<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Kubernetes\Application\ClusterSession;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Application\NamespaceView;
use LightManager\Module\Kubernetes\Application\ResourceCache;

/**
 * `k8s.namespaces` — przestrzenie nazw **znane sesji**.
 *
 * Nazwa kwerendy pochodzi z planu kroku i jest w niej jedno słowo, które trzeba
 * czytać dosłownie: **znane sesji**, a nie „istniejące w klastrze”. Różnica jest
 * zasadnicza i wynika wprost z reguły nr 4 kwerendy — spis wszystkich przestrzeni
 * wymagałby wywołania `kubectl get namespaces`, czyli obiegu do klastra, a żadne
 * wywołanie sieciowe nie pada w rysowaniu klatki (reguła nadrzędna Faz XVII
 * i XVIII). Kwerenda, która by o to poprosiła, albo blokowałaby klatkę, albo
 * odpowiadałaby „nie wiem” aż do skutku.
 *
 * Sesja zna przestrzenie z **dwóch źródeł, oba już przeczytane**: bieżącą
 * (wskazaną przez użytkownika albo wziętą z `kubeconfig`) oraz te, które stoją
 * w wierszach zasobów wczytanych do pamięci. To drugie źródło rośnie w miarę
 * oglądania klastra i jest dokładnie tym, czego potrzebuje `k8s.deploy-image`:
 * przestrzeni, w której użytkownik właśnie coś ogląda.
 *
 * Kto potrzebuje spisu pełnego, zamawia go **komendą** `k8s.get namespaces` —
 * bo to jest czynność trwająca dłużej od klatki, a czynności są komendami.
 */
final class NamespacesQuery implements QueryInterface
{
    public function __construct(
        private readonly ClusterSession $session,
        private readonly ResourceCache $resources,
    ) {
    }

    public function name(): string
    {
        return KubernetesSettings::ID . '.namespaces';
    }

    public function descriptionKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.query.namespaces';
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
        $current = $this->session->namespace()?->value;
        $seen = $this->resources->namespacesSeen();

        // Bieżąca stoi pierwsza, także wtedy, gdy nie widziano jej w żadnym
        // wierszu — jest znana sesji z definicji, bo to na nią sesja wskazuje.
        if ($current !== null && !in_array($current, $seen, true)) {
            array_unshift($seen, $current);
        }

        $view = new NamespaceView($seen, $current);

        return QueryResult::owned(KubernetesSettings::ID, $view, static function () use ($view): array {
            $rows = [];

            foreach ($view->names as $name) {
                $rows[] = [
                    'name' => $name,
                    'current' => $name === $view->current,
                ];
            }

            return $rows;
        });
    }
}
