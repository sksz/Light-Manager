<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Ssh\Application\Port\SshStatePort;
use LightManager\Module\Ssh\Domain\ValueObject\HostCredentials;

/**
 * Stan modułu sesji zdalnej w pamięci (kroki 48–50; kształt z kroku 60).
 *
 * Do kroku 60 nazywała się `StubHostBook` i niosła książkę hostów; adresy
 * przeniosły się do książki adresowej (`StubAddressBook`), a tu zostały dwie
 * rzeczy własne modułu: czym się przedstawia i gdzie ostatnio stał.
 *
 * Powód istnienia atrapy jest ten sam, co zawsze: **test nie ma prawa dotknąć
 * dokumentu stanu w katalogu domowym osoby, która go uruchamia.**
 */
final class StubSshState implements SshStatePort
{
    public int $saves = 0;

    /**
     * Zapamiętane katalogi zdalne (krok 49) — po jednym na wpis książki.
     *
     * Publiczne, bo test o nie pyta wprost: „ostatni katalog przeżywa ponowne
     * uruchomienie" sprawdza się tym, co atrapa dostała do zapamiętania.
     *
     * @var array<string, string>
     */
    public array $directories = [];

    /** @param array<string, HostCredentials> $credentials po identyfikatorze wpisu */
    public function __construct(
        public array $credentials = [],
    ) {
    }

    public function credentials(string $entryId, string $entryName = ''): HostCredentials
    {
        return $this->credentials[$entryId] ?? $this->credentials[$entryName] ?? new HostCredentials();
    }

    public function saveCredentials(string $entryId, HostCredentials $credentials): void
    {
        ++$this->saves;
        $this->credentials[$entryId] = $credentials;
    }

    public function lastDirectory(string $entryId, string $entryName = ''): ?string
    {
        return $this->directories[$entryId] ?? ($entryName === '' ? null : ($this->directories[$entryName] ?? null));
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
