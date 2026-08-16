<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation\Query;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Kubernetes\Application\ApiCatalog;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Application\ResourceCache;
use LightManager\Module\Kubernetes\Application\ResourceRow;
use LightManager\Module\Kubernetes\Application\ResourceView;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;

/**
 * `k8s.resources <rodzaj>` — wiersze zasobu wskazanego rodzaju wraz z etapem.
 *
 * **Jedyna kwerenda tego kroku z argumentem obowiązkowym**, i to jest tu
 * konieczne, a nie ozdobne: rodzajów jest w klastrze kilkadziesiąt (z CRD —
 * więcej), a wypisanie wszystkich naraz kosztowałoby tyle wywołań `kubectl`, ile
 * jest rodzajów. Pytający podaje adres z `k8s.kinds` (`pods`, `deployments.apps`)
 * i dostaje to, co **już** jest w pamięci.
 *
 * **Kwerenda nie zamawia odczytu i nie ma prawa go zamówić.** Rodzaj nieoglądany
 * jeszcze przez użytkownika oddaje etap `absent` i pustą listę — bo `load()`
 * zmienia stan, a kwerenda czyta i nie zmienia (reguła nr 1). Zamówienie jest
 * czynnością, więc idzie komendą `k8s.get`.
 */
final class ResourcesQuery implements QueryInterface
{
    private const ARGUMENT = 'kind';

    private const TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private readonly ApiCatalog $catalog,
        private readonly ResourceCache $cache,
    ) {
    }

    public function name(): string
    {
        return KubernetesSettings::ID . '.resources';
    }

    public function descriptionKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.query.resources';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . KubernetesSettings::ID . '.argument.kind',
                CommandArgumentKind::Text,
                required: true,
            ),
        ];
    }

    public function generation(): int
    {
        return self::VOLATILE;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $address = $input->has(self::ARGUMENT) ? $input->text(self::ARGUMENT) : '';
        $kind = $this->catalog->find($address);

        if ($kind === null) {
            return QueryResult::failed('module.' . KubernetesSettings::ID . '.query.unknownKind');
        }

        $view = new ResourceView(
            $kind,
            $this->cache->knows($kind) ? $this->cache->rowsOf($kind) : [],
            $this->cache->knows($kind),
            $this->cache->pending()?->equals($kind) === true,
            $this->cache->problemKey(),
        );

        return QueryResult::owned(KubernetesSettings::ID, $view, static function () use ($view): array {
            $stage = $view->stage();
            $rows = [];

            foreach ($view->rows as $row) {
                $rows[] = self::describe($row, $view->kind, $stage);
            }

            return $rows === [] ? [[
                'name' => '',
                'namespace' => '',
                'kind' => $view->kind->address(),
                'created' => '',
                'stage' => $stage,
                'problem' => $view->problemKey ?? '',
            ]] : $rows;
        });
    }

    /** @return array<string, string|int|bool> */
    private static function describe(ResourceRow $row, ResourceKind $kind, string $stage): array
    {
        $fields = [
            'name' => $row->name,
            'namespace' => $row->namespace ?? '',
            'kind' => $kind->address(),
            'created' => $row->createdAt === null ? '' : date(self::TIMESTAMP_FORMAT, $row->createdAt),
            'stage' => $stage,
            'problem' => '',
        ];

        // Kolumny własne rodzaju wchodzą **pod swoimi nazwami**, a nie pod
        // numerami: wiersz kwerendy czyta także człowiek, a `ready` mówi mu
        // więcej niż `column1`. Rodzaj spoza pakietów nie wnosi tu nic i to jest
        // ta sama zapisana cena, co przy trzech kolumnach ogólnych w panelu (11v).
        foreach ($row->values as $column => $value) {
            $fields[$column] = $value;
        }

        return $fields;
    }
}
