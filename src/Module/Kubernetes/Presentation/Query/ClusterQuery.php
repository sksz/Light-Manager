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
 * `k8s.cluster` — wersja klastra, wersja klienta i etap sesji.
 *
 * Adresu serwera **nie ma w wierszach i nie będzie**, choć plan kroku go
 * wymieniał. Powód wyszedł z kodu, a nie z ostrożności: `kubeconfig` niesie adres
 * razem z poświadczeniami do klastra, a moduł czyta stamtąd wyłącznie nazwy
 * kontekstów (`ClusterInfoParser`) — adres trzeba by dopiero wydobyć, czyli
 * **dołożyć odczyt** po to, żeby oddać obcemu modułowi punkt wejścia do cudzej
 * infrastruktury. Ta sama granica, którą `ssh.hosts` przykłada do ścieżki klucza
 * prywatnego.
 *
 * Pole `skewed` jest za to warte wiersza: różnica wersji klienta i serwera jest
 * powodem, dla którego `kubectl` zaczyna odmawiać rzeczy, które działały wczoraj.
 */
final class ClusterQuery implements QueryInterface
{
    public function __construct(
        private readonly ClusterState $cluster,
    ) {
    }

    public function name(): string
    {
        return KubernetesSettings::ID . '.cluster';
    }

    public function descriptionKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.query.cluster';
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

        return QueryResult::owned(KubernetesSettings::ID, $view, static fn (): array => [[
            'stage' => strtolower($view->stage->name),
            'context' => $view->current->value ?? '',
            'client' => $view->versions->client ?? '',
            'server' => $view->versions->server ?? '',
            'skewed' => $view->versions !== null && $view->versions->isSkewed(),
            'ready' => $view->isReady(),
            'problem' => $view->problemKey ?? '',
        ]]);
    }
}
