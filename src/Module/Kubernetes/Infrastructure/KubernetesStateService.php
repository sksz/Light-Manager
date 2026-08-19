<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Infrastructure;

use LightManager\Application\Port\StateDocumentPort;
use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Kubernetes\Application\Port\KubernetesStatePort;

/**
 * Stan modułu klastra — sekcja `k8s` dokumentu stanu (krok 59).
 *
 * Usłudze została **treść sekcji**: klucze `clusters` i `currentCluster` oraz
 * zamiana wiersza na wpis książki i z powrotem. Mechanizm — plik, zapis
 * tymczasowy z `rename()`, przetrwanie nieznanych kluczy i cudzych sekcji —
 * mieszka za rdzeniowym `StateDocumentPort` (wynik przeglądu 15e, D103). To
 * pierwsza usługa stanu w projekcie, która nie zawiera **ani jednej linii**
 * tamtego mechanizmu — i to jest wymierny skutek przeglądu.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu). Wiersz nie do przyjęcia wypada,
 * a sekcja zostaje — jeden zepsuty wpis nie odbiera użytkownikowi całej
 * książki.
 */
final class KubernetesStateService extends AbstractSingleton implements KubernetesStatePort
{
    private const SECTION = 'k8s';

    private const CLUSTERS_KEY = 'clusters';

    /** Nazwa wpisu bieżącego — wybór przeżywa uruchomienie. */
    private const CURRENT_KEY = 'currentCluster';

    /** Znacznik przeniesienia starego spisu do książki adresowej (krok 60). */
    private const MIGRATED_KEY = 'migrated';

    private const NAME_KEY = 'name';

    private const KUBECONFIG_KEY = 'kubeconfig';

    private const CONTEXT_KEY = 'context';

    private const NAMESPACE_KEY = 'namespace';

    private const TIMEOUT_KEY = 'timeout';

    private ?StateDocumentPort $documents = null;

    /**
     * Ostatnio wczytana sekcja — po to, żeby zapis nie skasował kluczy, których
     * ta wersja nie zna.
     *
     * @var array<string, mixed>|null
     */
    private ?array $section = null;

    private bool $sectionRead = false;

    /** Podstawienie dokumentu stanu — **wyłącznie dla testów** (szew jak w `KubectlService`). */
    public function useSeam(StateDocumentPort $documents): void
    {
        $this->documents = $documents;
        $this->section = null;
        $this->sectionRead = false;
    }

    public function current(): string
    {
        $current = ($this->section() ?? [])[self::CURRENT_KEY] ?? '';

        return is_string($current) ? $current : '';
    }

    public function makeCurrent(string $value): void
    {
        $section = $this->section() ?? [];

        if (($section[self::CURRENT_KEY] ?? null) === $value) {
            return;
        }

        $section[self::CURRENT_KEY] = $value;
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
    }

    /**
     * Stary spis klastrów — **czytany, nigdy niekasowany** (krok 60).
     *
     * Wiersze wychodzą stąd jako tablice napisów i liczb: przenosi je do
     * książki **komendami** ten, kto je tu zostawił.
     */
    public function legacyClusters(): array
    {
        $stored = ($this->section() ?? [])[self::CLUSTERS_KEY] ?? null;

        if (!is_array($stored)) {
            return [];
        }

        $clusters = [];

        foreach ($stored as $item) {
            if (!is_array($item)) {
                continue;
            }

            $cluster = self::legacyClusterFrom($item);

            if ($cluster !== null) {
                $clusters[] = $cluster;
            }
        }

        return $clusters;
    }

    public function isMigrated(): bool
    {
        return (($this->section() ?? [])[self::MIGRATED_KEY] ?? false) === true;
    }

    public function markMigrated(): void
    {
        $section = $this->section() ?? [];
        $section[self::MIGRATED_KEY] = true;
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
    }

    public function isFresh(): bool
    {
        $section = $this->section();

        return $section === null
            || (!isset($section[self::CLUSTERS_KEY]) && !isset($section[self::MIGRATED_KEY]));
    }

    /**
     * @param array<mixed> $item
     *
     * @return array<string, string|int>|null
     */
    private static function legacyClusterFrom(array $item): ?array
    {
        $name = $item[self::NAME_KEY] ?? null;
        $kubeconfig = $item[self::KUBECONFIG_KEY] ?? null;
        $context = $item[self::CONTEXT_KEY] ?? null;

        if (!is_string($name) || !is_string($kubeconfig) || !is_string($context)) {
            return null;
        }

        if ($name === '' || $kubeconfig === '' || $context === '') {
            return null;
        }

        $legacy = [
            self::NAME_KEY => $name,
            self::KUBECONFIG_KEY => $kubeconfig,
            self::CONTEXT_KEY => $context,
        ];

        foreach ([self::NAMESPACE_KEY, self::TIMEOUT_KEY] as $key) {
            $value = $item[$key] ?? null;

            if ((is_string($value) && $value !== '') || is_int($value)) {
                $legacy[$key] = $value;
            }
        }

        return $legacy;
    }

    public function location(): string
    {
        return $this->documents()->location();
    }

    /**
     * Sekcja z dokumentu stanu, przeczytana raz; `null` znaczy „nie da się jej
     * przeczytać".
     *
     * @return array<string, mixed>|null
     */
    private function section(): ?array
    {
        if (!$this->sectionRead) {
            $this->sectionRead = true;
            $this->section = $this->documents()->section(self::SECTION);
        }

        return $this->section;
    }

    private function documents(): StateDocumentPort
    {
        return $this->documents ?? StateDocumentService::getInstance();
    }
}
