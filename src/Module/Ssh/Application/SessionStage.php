<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

/**
 * Na czym stoi sesja w tej chwili (krok 48).
 *
 * Etapów jest sześć, bo tyle różnych rzeczy widzi użytkownik — i to jest jedyne
 * kryterium ich podziału. Dwa z nich (`Probing`, `AwaitingApproval`) istnieją
 * wyłącznie dla hosta **nieznanego**: host, którego odcisk stoi już
 * w `~/.ssh/known_hosts`, przechodzi z `Idle` wprost w `Connecting`.
 *
 * `AwaitingApproval` jest przy tym jedynym etapem, na którym **nic się nie
 * dzieje i dziać nie może**: żaden proces nie trwa, a stan czeka na człowieka.
 * Bez tego rozróżnienia takt nie umiałby odróżnić pracy trwającej od pracy
 * stojącej i albo kręciłby się w kółko, albo uznał sesję za zerwaną.
 */
enum SessionStage
{
    /** Nic nie trwa i nic nie jest połączone. */
    case Idle;

    /** Idzie `ssh-keyscan` po odcisk hosta, którego `known_hosts` nie zna. */
    case Probing;

    /** Odcisk jest, pytanie stoi na ekranie, praca czeka na człowieka. */
    case AwaitingApproval;

    /** Mistrz połączenia się zestawia — jedyny etap płacący za uścisk dłoni. */
    case Connecting;

    /**
     * Idzie `ssh -O check` — pytanie o to, czy gniazdo mistrza jeszcze żyje.
     *
     * Etap istnieje **wyłącznie na żądanie** (`F5`), a nie w takcie, i to jest
     * rozstrzygnięcie warte zapamiętania: pytanie co kilka sekund znaczyłoby
     * proces potomny co kilka sekund, a `BackgroundProcessPort` prowadzi **jedną
     * pracę naraz** (D87 nr 9) — czyli zabijałoby liczenie `du` w module opisu
     * pliku raz na te kilka sekund, na okrągło. Ceną jest to, że sesja zerwana
     * przez sieć pokazuje się jako żywa, dopóki ktoś nie zapyta.
     */
    case Checking;

    /** Gniazdo mistrza żyje i wpuszcza kolejne operacje bez uścisku. */
    case Connected;

    /** Ostatnia próba się nie udała; powód stoi w stanie. */
    case Failed;

    /** Czy na tym etapie coś trwa — czyli czy takt ma o co pytać. */
    public function isWorking(): bool
    {
        return $this === self::Probing || $this === self::Connecting || $this === self::Checking;
    }

    /** Klucz katalogu z nazwą etapu — kolumna „stan" w spisie hostów. */
    public function labelKey(): string
    {
        return 'module.ssh.stage.' . match ($this) {
            self::Idle => 'idle',
            self::Probing => 'probing',
            self::AwaitingApproval => 'approval',
            self::Connecting => 'connecting',
            self::Checking => 'checking',
            self::Connected => 'connected',
            self::Failed => 'failed',
        };
    }
}
