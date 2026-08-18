<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Infrastructure;

use LightManager\Application\Port\StateDocumentPort;
use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Kubernetes\Application\ClusterBook;
use LightManager\Module\Kubernetes\Application\Port\ClusterBookPort;
use LightManager\Module\Kubernetes\Application\Port\LoadedClusterBook;
use LightManager\Module\Kubernetes\Domain\Exception\InvalidClusterNameException;
use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterProfile;

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
final class KubernetesStateService extends AbstractSingleton implements ClusterBookPort
{
    private const SECTION = 'k8s';

    private const CLUSTERS_KEY = 'clusters';

    /** Nazwa wpisu bieżącego — wybór przeżywa uruchomienie. */
    private const CURRENT_KEY = 'currentCluster';

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

    public function load(): LoadedClusterBook
    {
        $section = $this->section();

        if ($section === null) {
            return new LoadedClusterBook(new ClusterBook(), 'module.k8s.cluster.book.unreadable');
        }

        $stored = $section[self::CLUSTERS_KEY] ?? null;

        if ($stored === null) {
            // Sekcji jeszcze nie ma albo nie ma w niej książki — to jest ta
            // chwila, w której wolno przenieść zapamiętane miejsce z ustawień
            // (plan, punkt 7).
            return new LoadedClusterBook(new ClusterBook(), null, fresh: true);
        }

        if (!is_array($stored)) {
            return new LoadedClusterBook(new ClusterBook(), 'module.k8s.cluster.book.unreadable');
        }

        $current = $section[self::CURRENT_KEY] ?? '';

        return new LoadedClusterBook(new ClusterBook(
            self::profilesFrom($stored),
            is_string($current) ? $current : '',
        ));
    }

    public function save(ClusterBook $book): void
    {
        $section = $this->section() ?? [];
        $section[self::CLUSTERS_KEY] = self::documentOf($book);
        $section[self::CURRENT_KEY] = $book->current();
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
    }

    public function location(): string
    {
        return $this->documents()->location();
    }

    /**
     * @param array<mixed> $stored
     *
     * @return list<ClusterProfile>
     */
    private static function profilesFrom(array $stored): array
    {
        $profiles = [];

        foreach ($stored as $item) {
            if (!is_array($item)) {
                continue;
            }

            $profile = self::profileFrom($item);

            if ($profile !== null) {
                $profiles[] = $profile;
            }
        }

        return $profiles;
    }

    /** @param array<mixed> $item */
    private static function profileFrom(array $item): ?ClusterProfile
    {
        $name = $item[self::NAME_KEY] ?? null;
        $kubeconfig = $item[self::KUBECONFIG_KEY] ?? null;
        $context = $item[self::CONTEXT_KEY] ?? null;

        if (!is_string($name) || !is_string($kubeconfig) || !is_string($context)) {
            return null;
        }

        $namespace = $item[self::NAMESPACE_KEY] ?? '';
        $timeout = $item[self::TIMEOUT_KEY] ?? null;

        try {
            return ClusterProfile::of(
                $name,
                $kubeconfig,
                $context,
                is_string($namespace) ? $namespace : '',
                is_int($timeout) && $timeout > 0 ? $timeout : null,
            );
        } catch (InvalidClusterNameException) {
            // Wpis nie do przyjęcia wypada; reszta książki jest w porządku i nie
            // ma powodu jej tracić. Port nie rzuca (reguła 8).
            return null;
        }
    }

    /** @return list<array<string, int|string>> */
    private static function documentOf(ClusterBook $book): array
    {
        $stored = [];

        foreach ($book->all() as $entry) {
            $item = [
                self::NAME_KEY => $entry->name,
                self::KUBECONFIG_KEY => $entry->kubeconfig,
                self::CONTEXT_KEY => $entry->context,
            ];

            // Pól pustych nie zapisujemy: sekcja ma się dać przeczytać oczami,
            // a `"namespace": ""` w każdym wpisie tylko ją zaśmieca.
            if ($entry->namespace !== '') {
                $item[self::NAMESPACE_KEY] = $entry->namespace;
            }

            if ($entry->timeoutSeconds !== null) {
                $item[self::TIMEOUT_KEY] = $entry->timeoutSeconds;
            }

            $stored[] = $item;
        }

        return $stored;
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
