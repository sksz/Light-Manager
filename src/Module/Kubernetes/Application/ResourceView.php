<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Module\Kubernetes\Domain\ValueObject\ResourceKind;

/**
 * Wiersze jednego rodzaju zasobu wraz z etapem pracy — migawka (krok 54).
 *
 * Etap jest tu polem osobnym i **trzystanowym**, bo brak wierszy ma w tym module
 * trzy różne znaczenia, których użytkownik nie odróżni z samej pustki: „nikt
 * jeszcze o ten rodzaj nie pytał", „właśnie pytam klastra" i „zapytałem, nie ma
 * ani jednego". Bez tego rozróżnienia `k8s.resources deployments.apps` przy
 * świeżo otwartym module wyglądałoby jak klaster bez wdrożeń.
 */
final readonly class ResourceView
{
    /** @param list<ResourceRow> $rows */
    public function __construct(
        public ResourceKind $kind,
        public array $rows,
        /** Czy odpowiedź klastra dla tego rodzaju przyszła choć raz. */
        public bool $loaded,
        /** Czy właśnie na nią czekamy. */
        public bool $working,
        public ?string $problemKey = null,
    ) {
    }

    public function stage(): string
    {
        return match (true) {
            $this->working => 'reading',
            $this->loaded => 'ready',
            default => 'absent',
        };
    }

    public function count(): int
    {
        return count($this->rows);
    }
}
