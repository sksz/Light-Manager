<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\Kubernetes\Application\ApiCatalog;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;

/**
 * `k8s.kinds` — rodzaje zasobów wzięte z `api-resources`.
 *
 * **Rodzajów nie ma w kodzie** (11v) i dlatego ta kwerenda jest ciekawsza, niż
 * wygląda: oddaje to, co klaster o sobie powiedział, więc definicje własne (CRD)
 * stoją w odpowiedzi obok wbudowanych i niczym się od nich nie różnią. Moduł
 * pytający dostaje przez to spis prawdziwy dla **tego** klastra, a nie spis
 * wpisany kiedyś do aplikacji.
 *
 * Pola `listable` i `deletable` biorą się z czasowników zgłoszonych przez
 * klaster, nie z domysłu — a bez nich pytający musiałby zgadywać, czy rodzaj,
 * który widzi, da się w ogóle wypisać.
 */
final class KindsQuery implements QueryInterface
{
    public function __construct(
        private readonly ApiCatalog $catalog,
    ) {
    }

    public function name(): string
    {
        return KubernetesSettings::ID . '.kinds';
    }

    public function descriptionKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.query.kinds';
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
        $catalog = $this->catalog;

        return QueryResult::owned(KubernetesSettings::ID, $catalog, static function () use ($catalog): array {
            $rows = [];

            foreach ($catalog->kinds() as $kind) {
                $rows[] = self::describe($kind);
            }

            return $rows === [] ? [[
                'name' => '',
                'kind' => '',
                'group' => '',
                'address' => '',
                'namespaced' => false,
                'listable' => false,
                'deletable' => false,
                'shortNames' => '',
                'problem' => $catalog->problemKey() ?? '',
            ]] : $rows;
        });
    }

    /** @return array<string, string|int|bool> */
    private static function describe(ResourceKind $kind): array
    {
        return [
            'name' => $kind->name,
            'kind' => $kind->kind,
            'group' => $kind->group,
            'address' => $kind->address(),
            'namespaced' => $kind->namespaced,
            'listable' => $kind->isListable(),
            'deletable' => $kind->isDeletable(),
            'shortNames' => implode(' ', $kind->shortNames),
            'problem' => '',
        ];
    }
}
