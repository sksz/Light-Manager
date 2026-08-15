<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Ssh\Application\Port\RemoteDirectoryPort;
use LightManager\Module\Ssh\Application\RemoteListingState;
use LightManager\Module\Ssh\Domain\Aggregate\RemoteDirectory;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\RemoteEntry;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;

/**
 * Zdalny katalog bez zdalnego hosta — bo **żaden test nie ma prawa otworzyć
 * połączenia** (krok 49).
 *
 * Atrapa powstała po tym, jak jej brak zrobił dokładnie to, przed czym broni
 * kryterium ukończenia kroku: przebieg funkcjonalny z podstawioną sesją wziął
 * **prawdziwy** `SftpDirectoryService` i wypuścił procesy `sftp` do hosta
 * z przykładowego wpisu książki. Test przestał się kończyć, a maszyna zaczęła
 * pukać do cudzego adresu.
 *
 * Odpowiada **natychmiast**, a nie po takcie, i to jest świadome uproszczenie
 * wobec prawdziwej usługi: opóźnienie sieci sprawdza się na atrapie portu pracy
 * tłowej (`StubBackgroundProcess`), a nie tutaj. Kto chce sprawdzić klatkę
 * z trwającym odczytem, woła `keepWorking()`.
 */
final class StubRemoteDirectory implements RemoteDirectoryPort
{
    /** Ile razy zamówiono odczyt — po tym poznaje się „nowy obieg”. */
    public int $reads = 0;

    /** Ostatnio zamówiona ścieżka; `null` znaczy „katalog startowy”. */
    public ?RemotePath $requested = null;

    /** Czy ostatnie zamówienie prosiło o wpisy ukryte. */
    public bool $withHidden = false;

    private RemoteListingState $state;

    /** @var array<string, list<RemoteEntry>> ścieżka → wpisy; `*` łapie każdą inną */
    private array $listings;

    private bool $working = false;

    private ?string $problemKey = null;

    /** @param array<string, list<RemoteEntry>> $listings */
    public function __construct(array $listings = [], private readonly string $home = '/home/anna')
    {
        $this->listings = $listings;
        $this->state = RemoteListingState::idle();
    }

    /** Każdy kolejny odczyt zatrzymuje się na etapie „czytam”. */
    public function keepWorking(bool $working = true): void
    {
        $this->working = $working;
    }

    /** Każdy kolejny odczyt kończy się tym powodem. */
    public function failWith(?string $problemKey): void
    {
        $this->problemKey = $problemKey;
    }

    public function state(): RemoteListingState
    {
        return $this->state;
    }

    public function begin(HostProfile $host, ?RemotePath $path, bool $includeHidden): void
    {
        ++$this->reads;
        $this->requested = $path;
        $this->withHidden = $includeHidden;

        $resolved = $path ?? RemotePath::of($this->home);

        if ($this->problemKey !== null) {
            $this->state = RemoteListingState::failed($resolved, $this->problemKey);

            return;
        }

        if ($this->working) {
            $this->state = RemoteListingState::listing($path);

            return;
        }

        $this->state = RemoteListingState::ready(new RemoteDirectory($resolved, $this->entriesFor($resolved)));
    }

    public function advance(): void
    {
    }

    public function stop(): void
    {
        $this->state = RemoteListingState::idle();
    }

    /** @return list<RemoteEntry> */
    private function entriesFor(RemotePath $path): array
    {
        return $this->listings[$path->value] ?? $this->listings['*'] ?? [];
    }
}
