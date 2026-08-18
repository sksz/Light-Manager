<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Docker\Application\ComposeAction;
use LightManager\Module\Docker\Application\ComposeState;
use LightManager\Module\Docker\Application\Port\ComposePort;
use LightManager\Module\Docker\Domain\ValueObject\ComposeProject;

/**
 * Wtyczka compose bez wtyczki (krok 51).
 *
 * Powód jest ostrzejszy niż przy atrapie gniazda: `docker compose up` **zmienia
 * stan maszyny**, na której akurat biegnie test — podnosi cudze kontenery, sięga
 * do sieci po obrazy. Żaden test tego kroku nie uruchamia klienta Dockera.
 *
 * Praca kończy się po zadanej liczbie posunięć, dokładnie jak w
 * `StubBackgroundProcess`: liczba posunięć jest tu **miarą czasu**, a każdy
 * użytkownik posuwa tę pracę raz na takt.
 */
final class StubCompose implements ComposePort
{
    /** @var list<string> czynności, o które poproszono — w kolejności */
    public array $started = [];

    /** Ostatni przedrostek środowiska (krok 58) — pusty, dopóki takt go nie pchnął. */
    public string $prefix = '';

    public int $stopCount = 0;

    private ComposeState $state;

    private int $advances = 0;

    public function __construct(
        /** Po ilu posunięciach praca się kończy. */
        private readonly int $advancesUntilDone = 1,
        /** Klucz powodu; ustawiony — praca kończy się niepowodzeniem. */
        private readonly ?string $problemKey = null,
        /** @var list<ComposeProject> spis, którym kończy się `ls` */
        private readonly array $projects = [],
    ) {
        $this->state = ComposeState::idle();
    }

    public function useEnvironment(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function state(): ComposeState
    {
        return $this->state;
    }

    public function begin(ComposeAction $action, ?string $file = null): void
    {
        $this->started[] = $action->value . ($file === null ? '' : ' ' . $file);
        $this->advances = 0;
        $this->state = ComposeState::working($action);
    }

    public function advance(): void
    {
        if (!$this->state->isWorking() || $this->state->action === null) {
            return;
        }

        if (++$this->advances < $this->advancesUntilDone) {
            return;
        }

        $action = $this->state->action;

        $this->state = $this->problemKey === null
            ? ComposeState::done($action, $action === ComposeAction::ListProjects ? $this->projects : [])
            : ComposeState::failed($action, $this->problemKey);
    }

    public function stop(): void
    {
        ++$this->stopCount;
        $this->advances = 0;
        $this->state = ComposeState::idle();
    }
}
