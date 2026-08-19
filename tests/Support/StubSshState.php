<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Ssh\Application\Port\SshStatePort;

/**
 * Sekcja `ssh` dokumentu stanu w pamięci — bo test nie ma prawa dotknąć
 * `~/.light-manager/`.
 *
 * Ta sama zasada, co przy `StubPlaylistStorage` z kroku 45: plik w katalogu
 * domowym należy do użytkownika, a nie do przebiegu testów. Prawdziwej usłudze
 * (`SshStateService`) zostaje to, czego atrapą sprawdzić się nie da — zapis
 * przez plik tymczasowy i przeżywanie nieznanych kluczy — i to sprawdza się na
 * katalogu tymczasowym, nie na domowym.
 *
 * **Od kroku 60 nie ma tu książki**: wpisy wyprowadziły się do wspólnego
 * rejestru, a sekcji został zapamiętany katalog per **identyfikator wpisu**
 * oraz stary spis, który migracja ma przenieść.
 */
final class StubSshState implements SshStatePort
{
    public bool $migrated = false;

    /**
     * Zapamiętane katalogi zdalne (krok 49) — po jednym na **identyfikator**
     * wpisu książki.
     *
     * Publiczne, bo test o nie pyta wprost: „ostatni katalog przeżywa ponowne
     * uruchomienie" sprawdza się tym, co atrapa dostała do zapamiętania.
     *
     * @var array<string, string>
     */
    public array $directories = [];

    /** @param list<array<string, string|int>> $legacy stary spis do przeniesienia */
    public function __construct(private readonly array $legacy = [])
    {
    }

    public function legacyHosts(): array
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

    public function lastDirectory(string $entryId): ?string
    {
        return $this->directories[$entryId] ?? null;
    }

    public function rememberDirectory(string $entryId, string $path): void
    {
        $this->directories[$entryId] = $path;
    }

    public function location(): string
    {
        return '/tmp/light-manager-test/state.json';
    }
}
