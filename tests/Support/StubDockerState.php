<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Docker\Application\Port\DockerStatePort;

/**
 * Sekcja `docker` dokumentu stanu w pamięci — bo test nie ma prawa czytać ani
 * pisać `~/.light-manager/state.json` maszyny, na której akurat biegnie.
 *
 * **Od kroku 60 nie ma tu książki**: środowiska wyprowadziły się do wspólnego
 * rejestru, a sekcji został wskaźnik bieżącego środowiska, znacznik
 * przeniesienia i stary spis, który migracja ma przenieść.
 */
final class StubDockerState implements DockerStatePort
{
    public bool $migrated = false;

    public string $current = '';

    /** @param list<array<string, string|int>> $legacy stary spis do przeniesienia */
    public function __construct(private readonly array $legacy = [])
    {
    }

    public function current(): string
    {
        return $this->current;
    }

    public function makeCurrent(string $value): void
    {
        $this->current = $value;
    }

    public function legacyEnvironments(): array
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

    public function location(): string
    {
        return '/tmp/light-manager-test/state.json';
    }
}
