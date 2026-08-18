<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Module\Kubernetes\Application\ApiCatalog;
use LightManager\Module\Kubernetes\Application\ClustersView;
use LightManager\Module\Kubernetes\Application\ClusterView;
use LightManager\Module\Kubernetes\Application\DeploymentView;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Application\NamespaceView;
use LightManager\Module\Kubernetes\Application\ResourceRow;
use LightManager\Module\Kubernetes\Application\ResourceView;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;

/**
 * Odczyt danych modułu Kubernetesa — **przez rejestr kwerend, jak każdy inny**
 * (krok 53, D92 nr 3; ten moduł dostał go w kroku 54).
 *
 * Szósta i ostatnia fasada modułowa w projekcie — a wraz z nią miara druga kroku
 * 54 jest rozstrzygnięta: **sześć modułów, sześć fasad, zero zmian
 * w `Application/Query/`.**
 *
 * Jedna rzecz odróżnia ją od pięciu poprzednich: `resources()` bierze
 * **argument**, bo jedyna kwerenda tego kroku z argumentem obowiązkowym mieszka
 * właśnie tutaj. Fasada składa `CommandInput` sama, więc wołający podaje rodzaj,
 * a nie wiersz polecenia.
 */
final readonly class KubernetesQueries
{
    public function __construct(
        private QueryRegistry $queries,
    ) {
    }

    public function cluster(): ClusterView
    {
        $payload = $this->ask('cluster');

        return $payload instanceof ClusterView ? $payload : ClusterView::empty();
    }

    /** Ten sam ładunek, co `cluster()` — spis kontekstów pochodzi z tego samego odczytu. */
    public function contexts(): ClusterView
    {
        $payload = $this->ask('contexts');

        return $payload instanceof ClusterView ? $payload : ClusterView::empty();
    }

    /** Spis klastrów z obu źródeł — treść czwartej postaci ekranu (krok 59). */
    public function clusters(): ClustersView
    {
        $payload = $this->ask('clusters');

        return $payload instanceof ClustersView ? $payload : ClustersView::empty();
    }

    public function namespaces(): NamespaceView
    {
        $payload = $this->ask('namespaces');

        return $payload instanceof NamespaceView ? $payload : NamespaceView::empty();
    }

    public function kinds(): ?ApiCatalog
    {
        $payload = $this->ask('kinds');

        return $payload instanceof ApiCatalog ? $payload : null;
    }

    public function resources(ResourceKind $kind): ResourceView
    {
        $input = new CommandInput(['kind' => $kind->address()]);
        $payload = $this->queries
            ->ask(KubernetesSettings::ID . '.resources', $input)
            ->payloadFor(KubernetesSettings::ID);

        return $payload instanceof ResourceView ? $payload : new ResourceView($kind, [], false, false);
    }

    public function deployments(): DeploymentView
    {
        $payload = $this->ask('deployments');

        return $payload instanceof DeploymentView ? $payload : DeploymentView::empty();
    }

    /**
     * Wiersze rodzaju — skrót na `resources()`, bo panel i drzewo pytają o nie
     * w każdej klatce.
     *
     * @return list<ResourceRow>
     */
    public function rowsOf(ResourceKind $kind): array
    {
        return $this->resources($kind)->rows;
    }

    /** Czy odpowiedź klastra dla tego rodzaju przyszła choć raz. */
    public function knows(ResourceKind $kind): bool
    {
        return $this->resources($kind)->loaded;
    }

    /**
     * Grupy API zgłoszone przez klaster — źródło pierwszego piętra drzewa.
     *
     * @return list<string>
     */
    public function groups(): array
    {
        return $this->kinds()?->groups() ?? [];
    }

    /**
     * Rodzaje jednej grupy.
     *
     * @return list<ResourceKind>
     */
    public function kindsOf(string $group): array
    {
        return $this->kinds()?->kindsOf($group) ?? [];
    }

    public function findKind(string $address): ?ResourceKind
    {
        return $this->kinds()?->find($address);
    }

    private function ask(string $name): ?object
    {
        return $this->queries->ask(KubernetesSettings::ID . '.' . $name)->payloadFor(KubernetesSettings::ID);
    }
}
