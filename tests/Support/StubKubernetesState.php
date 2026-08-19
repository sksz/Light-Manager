<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Kubernetes\Application\Port\KubernetesStatePort;

/**
 * Sekcja `k8s` dokumentu stanu w pamięci — bo test nie ma prawa czytać ani
 * pisać dokumentu stanu maszyny, na której akurat biegnie.
 *
 * **Od kroku 60 nie ma tu książki**: klastry wyprowadziły się do wspólnego
 * rejestru, a sekcji został wskaźnik bieżącego miejsca, znacznik przeniesienia
 * i stary spis, który migracja ma przenieść.
 */
final class StubKubernetesState implements KubernetesStatePort
{
    public bool $migrated = false;

    public string $current = '';

    /**
     * @param list<array<string, string|int>> $legacy stary spis do przeniesienia
     * @param bool                            $fresh  czy sekcja jest świeża — warunek
     *                                                migracji pozycji ustawień z kroku 59
     */
    public function __construct(
        private readonly array $legacy = [],
        private readonly bool $fresh = true,
    ) {
    }

    public function current(): string
    {
        return $this->current;
    }

    public function makeCurrent(string $value): void
    {
        $this->current = $value;
    }

    public function legacyClusters(): array
    {
        return $this->legacy;
    }

    public function isMigrated(): bool
    {
        return $this->migrated;
    }

    public function markMigrated(): void
    {
        $this->migrated = true;
    }

    public function isFresh(): bool
    {
        return $this->fresh && $this->legacy === [];
    }

    public function location(): string
    {
        return '/tmp/light-manager-test/state.json';
    }
}
