<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Ssh\Application\Port\SshSessionPort;
use LightManager\Module\Ssh\Application\SessionState;
use LightManager\Module\Ssh\Domain\ValueObject\HostFingerprint;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;

/**
 * Sesja zdalna, która nigdzie się nie łączy.
 *
 * Powód jest ostrzejszy niż przy `StubAudio`: tam prawdziwa usługa zaczęłaby
 * grać na maszynie, na której akurat biegnie test, tutaj **wyszłaby do sieci** —
 * a test sięgający poza maszynę nie jest testem, tylko zakładem o cudzy serwer.
 * Reguła całej Fazy XVII brzmi wprost: żaden test nie otwiera połączenia
 * (D84, D87).
 *
 * Atrapa jest przy tym **sterowalna, a nie milcząca**, bo cała rzecz, którą
 * trzeba w module sprawdzić, to reakcja na kolejne etapy pracy: `settle()`
 * ustawia to, czym skończy się najbliższe `advance()`. Bez tego nie dałoby się
 * sprawdzić ani pytania o odcisk, ani zdarzeń modułu.
 */
final class StubSshSession implements SshSessionPort
{
    private SessionState $state;

    /** Stan, na który przejdzie najbliższe `advance()`; `null` — praca trwa dalej. */
    private ?SessionState $next = null;

    /** @var list<array{host: string, password: ?string}> prośby o połączenie, w kolejności */
    public array $connects = [];

    public int $approvals = 0;

    public int $disconnects = 0;

    public int $refreshes = 0;

    public int $shutdowns = 0;

    /** @var list<array{timeout: int, remembers: bool}> */
    public array $options = [];

    public function __construct()
    {
        $this->state = SessionState::idle();
    }

    public function state(): SessionState
    {
        return $this->state;
    }

    public function useOptions(int $timeoutSeconds, bool $mayRememberHostKeys): void
    {
        $this->options[] = ['timeout' => $timeoutSeconds, 'remembers' => $mayRememberHostKeys];
    }

    public function connect(HostProfile $profile, ?string $password = null): void
    {
        $this->connects[] = ['host' => $profile->name, 'password' => $password];
        $this->state = SessionState::connecting($profile);
    }

    public function approve(): void
    {
        ++$this->approvals;

        $host = $this->state->host;

        if ($host !== null) {
            $this->state = SessionState::connecting($host);
        }
    }

    public function advance(): void
    {
        if ($this->next === null) {
            return;
        }

        $this->state = $this->next;
        $this->next = null;
    }

    public function refresh(): void
    {
        ++$this->refreshes;
    }

    public function disconnect(): void
    {
        ++$this->disconnects;
        $this->next = null;
        $this->state = SessionState::idle();
    }

    public function shutdown(): void
    {
        ++$this->shutdowns;
        $this->state = SessionState::idle();
    }

    /** Czym skończy się najbliższe `advance()` — sterowanie atrapą z testu. */
    public function settle(SessionState $state): void
    {
        $this->next = $state;
    }

    /** Skrót na najczęstszy przypadek: praca kończy się połączeniem. */
    public function settleConnected(HostProfile $profile): void
    {
        $this->settle(SessionState::connected($profile));
    }

    /** Skrót: praca kończy się pytaniem o nieznany odcisk. */
    public function settleAwaitingApproval(HostProfile $profile): void
    {
        $this->settle(SessionState::awaitingApproval($profile, [
            new HostFingerprint('ED25519', 'SHA256:0123456789abcdefghijklmnopqrstuvwxyzABCDEFG', 256),
        ]));
    }

    /** Skrót: praca kończy się niepowodzeniem. */
    public function settleFailed(HostProfile $profile, string $problemKey = 'module.ssh.problem.failed'): void
    {
        $this->settle(SessionState::failed($profile, $problemKey));
    }
}
