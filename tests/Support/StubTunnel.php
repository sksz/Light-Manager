<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Docker\Application\Port\TunnelPort;
use LightManager\Module\Docker\Application\TunnelStage;
use LightManager\Module\Docker\Application\TunnelState;

/**
 * Tunel SSH bez klienta i bez sieci (krok 58).
 *
 * Test nie ma prawa uruchomić `ssh` — a tunel, który by wstał, dawałby cudzej
 * maszynie gniazdo demona. Atrapa przechodzi przez te same cztery postacie, co
 * prawdziwa usługa: `open()` znaczy „wstaje", a po zadanej liczbie posunięć
 * przychodzi postać podana z góry — „stoi" albo „nie wstał z powodem".
 */
final class StubTunnel implements TunnelPort
{
    /** @var list<string> zamówienia tunelu: `nazwa cel port gniazdo` — w kolejności */
    public array $opened = [];

    /**
     * Czy kolejne zamówienia niosły hasło — **sam fakt, nigdy treść** (D102
     * nr 4): atrapa zapisująca hasło uczyłaby testy sprawdzania sekretu
     * w miejscu, w którym prawdziwa usługa go nie trzyma.
     *
     * @var list<bool>
     */
    public array $passwords = [];

    public int $closeCount = 0;

    private TunnelState $state;

    private int $advances = 0;

    public function __construct(
        private readonly int $advancesUntilDone = 1,
        /** Klucz powodu; ustawiony — tunel nie wstaje. */
        private readonly ?string $problemKey = null,
        private readonly string $socketPath = '/run/user/1000/lm-docker-test.sock',
    ) {
        $this->state = TunnelState::none();
    }

    public function open(string $name, string $target, int $port, string $remoteSocket, ?string $password = null): void
    {
        $this->opened[] = $name . ' ' . $target . ' ' . $port . ' ' . $remoteSocket;
        $this->passwords[] = $password !== null;
        $this->advances = 0;
        $this->state = TunnelState::starting();
    }

    public function advance(): void
    {
        if ($this->state->stage !== TunnelStage::Starting) {
            return;
        }

        if (++$this->advances < $this->advancesUntilDone) {
            return;
        }

        $this->state = $this->problemKey === null
            ? TunnelState::up($this->socketPath)
            : TunnelState::failed($this->problemKey, ['reason' => 'stub']);
    }

    public function state(): TunnelState
    {
        return $this->state;
    }

    public function close(): void
    {
        ++$this->closeCount;
        $this->state = TunnelState::none();
    }
}
