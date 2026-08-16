<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Infrastructure;

use LightManager\Module\Kubernetes\Application\ResourceColumn;
use LightManager\Module\Kubernetes\Application\RowTone;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;

/**
 * Co dany rodzaj zasobu pokazuje ponad nazwę, przestrzeń i wiek (krok 52).
 *
 * **To jest cena rozstrzygnięcia D91 nr 4, wypisana wprost.** Użytkownik wybrał
 * kolumny liczone z JSON-a zamiast tabeli drukowanej przez serwer, więc rodzaj
 * ma tyle kolumn, ile ktoś tutaj napisał — a rodzaj spoza spisu (każdy CRD)
 * dostaje trzy ogólne. Odwrotna droga dawała kolumny wszystkim za darmo
 * i przegrała parserem tekstu wyrównanego spacjami.
 *
 * Rachunek gotowości poda **powtarza to, co liczy serwer**, i jest to drugi
 * skutek tamtego rozstrzygnięcia, zapisany, żeby nikogo nie zaskoczył: `1/1`
 * w kolumnie `READY` bierze się tutaj z `status.containerStatuses`, a nie
 * z odpowiedzi serwera — więc przy rzadkich stanach (pod usuwany, kontener
 * inicjujący) liczba potrafi różnić się od tej, którą wypisze `kubectl get`.
 *
 * Rodzaje rozpoznajemy po **adresie** (`pods`, `deployments.apps`), a nie po
 * nazwie: `events` istnieje w dwóch grupach naraz i znaczy w nich co innego.
 */
final class ResourceColumnPacks
{
    /**
     * Kolumny własne rodzaju.
     *
     * @return list<ResourceColumn>
     */
    public static function columnsFor(ResourceKind $kind): array
    {
        return match ($kind->address()) {
            'pods' => [
                ResourceColumn::Ready,
                ResourceColumn::Status,
                ResourceColumn::Restarts,
                ResourceColumn::Node,
            ],
            'deployments.apps', 'statefulsets.apps' => [
                ResourceColumn::Ready,
                ResourceColumn::UpToDate,
                ResourceColumn::Available,
            ],
            'daemonsets.apps', 'replicasets.apps' => [ResourceColumn::Ready, ResourceColumn::Available],
            'services' => [ResourceColumn::Type, ResourceColumn::ClusterIp, ResourceColumn::Ports],
            'secrets' => [ResourceColumn::Type, ResourceColumn::Data],
            'configmaps' => [ResourceColumn::Data],
            'nodes' => [ResourceColumn::Status],
            'namespaces' => [ResourceColumn::Status],
            'persistentvolumeclaims' => [ResourceColumn::Status],
            'jobs.batch' => [ResourceColumn::Ready],
            default => [],
        };
    }

    /**
     * Wartości tych kolumn dla jednego zasobu.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    public static function valuesFor(ResourceKind $kind, array $item): array
    {
        return match ($kind->address()) {
            'pods' => self::podValues($item),
            'deployments.apps', 'statefulsets.apps' => self::workloadValues($item),
            'daemonsets.apps' => self::daemonValues($item),
            'replicasets.apps' => self::replicaSetValues($item),
            'services' => self::serviceValues($item),
            'secrets' => [
                ResourceColumn::Type->value => ClusterJson::text($item, 'type') ?? '',
                ResourceColumn::Data->value => (string) count(ClusterJson::map($item, 'data')),
            ],
            'configmaps' => [ResourceColumn::Data->value => (string) count(ClusterJson::map($item, 'data'))],
            'nodes' => [ResourceColumn::Status->value => self::nodeCondition($item)],
            'namespaces', 'persistentvolumeclaims' => [
                ResourceColumn::Status->value => ClusterJson::text($item, 'status', 'phase') ?? '',
            ],
            'jobs.batch' => [
                ResourceColumn::Ready->value => sprintf(
                    '%d/%d',
                    ClusterJson::number($item, 'status', 'succeeded') ?? 0,
                    ClusterJson::number($item, 'spec', 'completions') ?? 1,
                ),
            ],
            default => [],
        };
    }

    /**
     * Czy zasób woła o uwagę.
     *
     * Ton liczymy **wyłącznie dla rodzajów, dla których znaczy coś pewnego**.
     * Zasób, o którego zdrowiu nic nie wiemy, zostaje zwykły — kolorowanie „na
     * wszelki wypadek” zamieniłoby listę w choinkę, na której nie widać
     * prawdziwego kłopotu.
     *
     * @param array<string, mixed> $item
     */
    public static function toneFor(ResourceKind $kind, array $item): RowTone
    {
        if ($kind->address() === 'pods') {
            return self::podTone($item);
        }

        if ($kind->address() === 'nodes') {
            return self::nodeCondition($item) === 'Ready' ? RowTone::Normal : RowTone::Broken;
        }

        if (in_array($kind->address(), ['namespaces', 'persistentvolumeclaims'], true)) {
            return match (ClusterJson::text($item, 'status', 'phase')) {
                'Active', 'Bound' => RowTone::Normal,
                'Pending' => RowTone::Waiting,
                null => RowTone::Normal,
                default => RowTone::Broken,
            };
        }

        return RowTone::Normal;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private static function podValues(array $item): array
    {
        $statuses = ClusterJson::objects($item, 'status', 'containerStatuses');
        $ready = 0;
        $restarts = 0;

        foreach ($statuses as $status) {
            if (ClusterJson::dig($status, 'ready') === true) {
                ++$ready;
            }

            $restarts += ClusterJson::number($status, 'restartCount') ?? 0;
        }

        return [
            ResourceColumn::Ready->value => sprintf('%d/%d', $ready, count($statuses)),
            ResourceColumn::Status->value => self::podStatus($item),
            ResourceColumn::Restarts->value => (string) $restarts,
            ResourceColumn::Node->value => ClusterJson::text($item, 'spec', 'nodeName') ?? '',
        ];
    }

    /**
     * Stan poda: powód czekania kontenera, a dopiero w jego braku — faza.
     *
     * Kolejność jest odwrotna do intuicyjnej i taka być musi. Faza poda
     * w `CrashLoopBackOff` brzmi `Running`, bo z punktu widzenia klastra pod
     * **jest** uruchomiony — restartuje się w kółko, ale jest. Wypisanie fazy
     * dałoby więc listę samych zielonych „Running” przy podach, które nie
     * działają ani chwili; `kubectl get` z tego samego powodu pokazuje tu powód,
     * a nie fazę.
     *
     * @param array<string, mixed> $item
     */
    private static function podStatus(array $item): string
    {
        foreach (ClusterJson::objects($item, 'status', 'containerStatuses') as $status) {
            $reason = ClusterJson::text($status, 'state', 'waiting', 'reason')
                ?? ClusterJson::text($status, 'state', 'terminated', 'reason');

            if ($reason !== null && $reason !== '' && $reason !== 'Completed') {
                return $reason;
            }
        }

        // Pod w trakcie usuwania ma znacznik chwili skasowania i fazę `Running`
        // aż do końca — bez tego wiersza znikanie poda wyglądałoby jak zawieszenie.
        if (ClusterJson::text($item, 'metadata', 'deletionTimestamp') !== null) {
            return 'Terminating';
        }

        return ClusterJson::text($item, 'status', 'phase') ?? '';
    }

    /** @param array<string, mixed> $item */
    private static function podTone(array $item): RowTone
    {
        $status = self::podStatus($item);

        if (in_array($status, ['Running', 'Succeeded', 'Completed', ''], true)) {
            return RowTone::Normal;
        }

        return in_array($status, ['Pending', 'ContainerCreating', 'PodInitializing', 'Terminating'], true)
            ? RowTone::Waiting
            : RowTone::Broken;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private static function workloadValues(array $item): array
    {
        return [
            ResourceColumn::Ready->value => sprintf(
                '%d/%d',
                ClusterJson::number($item, 'status', 'readyReplicas') ?? 0,
                ClusterJson::number($item, 'spec', 'replicas') ?? 0,
            ),
            ResourceColumn::UpToDate->value => (string) (ClusterJson::number($item, 'status', 'updatedReplicas') ?? 0),
            ResourceColumn::Available->value => (string) (ClusterJson::number(
                $item,
                'status',
                'availableReplicas',
            ) ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private static function daemonValues(array $item): array
    {
        return [
            ResourceColumn::Ready->value => sprintf(
                '%d/%d',
                ClusterJson::number($item, 'status', 'numberReady') ?? 0,
                ClusterJson::number($item, 'status', 'desiredNumberScheduled') ?? 0,
            ),
            ResourceColumn::Available->value => (string) (ClusterJson::number(
                $item,
                'status',
                'numberAvailable',
            ) ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private static function replicaSetValues(array $item): array
    {
        return [
            ResourceColumn::Ready->value => sprintf(
                '%d/%d',
                ClusterJson::number($item, 'status', 'readyReplicas') ?? 0,
                ClusterJson::number($item, 'spec', 'replicas') ?? 0,
            ),
            ResourceColumn::Available->value => (string) (ClusterJson::number(
                $item,
                'status',
                'availableReplicas',
            ) ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private static function serviceValues(array $item): array
    {
        $ports = [];

        foreach (ClusterJson::objects($item, 'spec', 'ports') as $port) {
            $number = ClusterJson::number($port, 'port');

            if ($number === null) {
                continue;
            }

            $nodePort = ClusterJson::number($port, 'nodePort');
            $protocol = ClusterJson::text($port, 'protocol') ?? 'TCP';
            $ports[] = ($nodePort === null ? (string) $number : $number . ':' . $nodePort) . '/' . $protocol;
        }

        return [
            ResourceColumn::Type->value => ClusterJson::text($item, 'spec', 'type') ?? '',
            ResourceColumn::ClusterIp->value => ClusterJson::text($item, 'spec', 'clusterIP') ?? '',
            ResourceColumn::Ports->value => implode(',', $ports),
        ];
    }

    /**
     * Warunek `Ready` węzła — jedyny, o który pyta się patrząc na listę.
     *
     * @param array<string, mixed> $item
     */
    private static function nodeCondition(array $item): string
    {
        foreach (ClusterJson::objects($item, 'status', 'conditions') as $condition) {
            if (ClusterJson::text($condition, 'type') !== 'Ready') {
                continue;
            }

            return ClusterJson::text($condition, 'status') === 'True' ? 'Ready' : 'NotReady';
        }

        return '';
    }
}
