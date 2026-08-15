<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundState;
use LightManager\Application\Port\BackgroundProcessPort;

/**
 * Praca tłowa bez procesu: kończy się po zadanej liczbie doglądań.
 *
 * Powód jest ten sam, co przy `StubChecksums`, ale stawka wyższa: test, który
 * uruchamiałby prawdziwe `du`, zależałby od zawartości dysku maszyny, na której
 * akurat biegnie, a przy okazji zostawiałby po sobie procesy, gdyby przestał
 * przechodzić w połowie. Tutaj liczba doglądań jest wprost powiedziana, więc da
 * się sprawdzić to, co naprawdę jest do sprawdzenia: **że praca jest doglądana
 * co klatkę, że da się ją przerwać i że nikt nie czeka na jej koniec**.
 *
 * Prawdziwej usługi pilnuje osobny zestaw testów (`BackgroundProcessServiceTest`)
 * i tam procesy są prawdziwe — bo tam właśnie one są tematem.
 */
final class StubBackgroundProcess implements BackgroundProcessPort
{
    /** @var list<string> polecenia, o które poproszono — w kolejności */
    public array $startedCommands = [];

    /** @var list<int> limity czasu podane przy uruchamianiu */
    public array $timeouts = [];

    public int $stopCount = 0;

    private ?BackgroundHandle $current = null;

    private BackgroundState $state;

    private int $polls = 0;

    private int $lastId = 0;

    public function __construct(
        /** Po ilu doglądaniach praca się kończy. */
        private readonly int $pollsUntilDone = 2,
        private readonly string $output = "4096\t/home",
        private readonly int $exitCode = 0,
        /** Klucz powodu; ustawiony — praca nie rusza w ogóle. */
        private readonly ?string $problemKey = null,
        /** Strumień błędów polecenia — od kroku 49 port niesie go osobno. */
        private readonly string $errorOutput = '',
    ) {
        $this->state = BackgroundState::idle();
    }

    public function start(string $command, int $timeoutSeconds): BackgroundHandle
    {
        $this->startedCommands[] = $command;
        $this->timeouts[] = $timeoutSeconds;
        $this->polls = 0;
        $this->current = new BackgroundHandle(++$this->lastId);
        $this->state = $this->problemKey === null
            ? BackgroundState::running()
            : BackgroundState::failed($this->problemKey);

        return $this->current;
    }

    public function poll(BackgroundHandle $handle): BackgroundState
    {
        if ($this->current === null || !$this->current->equals($handle)) {
            return BackgroundState::idle();
        }

        if (!$this->state->isRunning()) {
            return $this->state;
        }

        ++$this->polls;

        return $this->state = $this->polls >= $this->pollsUntilDone
            ? BackgroundState::done($this->output, $this->exitCode, $this->errorOutput)
            : BackgroundState::running();
    }

    public function stop(BackgroundHandle $handle): void
    {
        if ($this->current === null || !$this->current->equals($handle)) {
            return;
        }

        ++$this->stopCount;
        $this->current = null;
        $this->polls = 0;
        $this->state = BackgroundState::idle();
    }
}
