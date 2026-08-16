<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Kubernetes\Application\ApiCatalog;
use LightManager\Module\Kubernetes\Application\DeploymentView;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Application\ResourceCache;
use LightManager\Module\Kubernetes\Application\ResourceColumn;

/**
 * `k8s.deployments` — wdrożenia wraz z **obrazem każdego kontenera**.
 *
 * **Piąty etap czynności `k8s.deploy-image` stoi na tej kwerendzie**, a wraz
 * z nim jedno rozstrzygnięcie kształtu: wiersz opisuje **kontener**, a nie
 * wdrożenie. Powód jest po stronie tego, co potem następuje —
 * `kubectl set image deployment/<nazwa> <kontener>=<obraz>` wymaga nazwy
 * kontenera, więc odpowiedź, która by jej nie niosła, kazałaby pytać drugi raz.
 * Wdrożenie o trzech kontenerach ma tu trzy wiersze i to jest zamierzone.
 *
 * Odczytu **nie zamawia** (reguła nr 1): rodzaj nieoglądany jeszcze przez
 * użytkownika oddaje etap `absent`. Zamówienie jest czynnością i idzie komendą
 * `k8s.get deployments.apps`.
 */
final class DeploymentsQuery implements QueryInterface
{
    /** Adres rodzaju w `api-resources` — grupa `apps` od Kubernetesa 1.16. */
    public const KIND_ADDRESS = 'deployments.apps';

    public function __construct(
        private readonly ApiCatalog $catalog,
        private readonly ResourceCache $cache,
    ) {
    }

    public function name(): string
    {
        return KubernetesSettings::ID . '.deployments';
    }

    public function descriptionKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.query.deployments';
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
        $kind = $this->catalog->find(self::KIND_ADDRESS);

        if ($kind === null) {
            // Klaster nie zgłosił rodzaju `deployments.apps` — albo katalog
            // jeszcze nie przyszedł, albo to nie jest klaster, na którym da się
            // cokolwiek wdrożyć. Jedno i drugie jest brakiem odpowiedzi, a nie
            // wyjątkiem (reguła 8).
            return QueryResult::failed('module.' . KubernetesSettings::ID . '.query.noDeployments');
        }

        $view = new DeploymentView(
            $this->cache->knows($kind) ? $this->cache->rowsOf($kind) : [],
            $this->cache->knows($kind),
            $this->cache->pending()?->equals($kind) === true,
            $this->cache->problemKey(),
        );

        return QueryResult::owned(KubernetesSettings::ID, $view, static function () use ($view): array {
            $stage = $view->stage();
            $rows = [];

            foreach ($view->rows as $row) {
                foreach ($row->images as $container => $image) {
                    $rows[] = [
                        'deployment' => $row->name,
                        'namespace' => $row->namespace ?? '',
                        'container' => $container,
                        'image' => $image,
                        'ready' => $row->valueOf(ResourceColumn::Ready),
                        'stage' => $stage,
                        'problem' => '',
                    ];
                }
            }

            return $rows === [] ? [[
                'deployment' => '',
                'namespace' => '',
                'container' => '',
                'image' => '',
                'ready' => '',
                'stage' => $stage,
                'problem' => $view->problemKey ?? '',
            ]] : $rows;
        });
    }
}
