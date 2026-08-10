<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Port\TerminalPort;

/**
 * Terminal sterowany scenariuszem: oddaje z góry zadane klawisze, a potem
 * `null`. Pozwala przetestować pętlę bez dotykania prawdziwego terminala.
 */
final class ScriptedTerminal implements TerminalPort
{
    /** @var list<KeyPress|null> */
    private array $script;

    private bool $shutdownRequested = false;

    private int $shutdownAfterReads;

    private int $reads = 0;

    /**
     * @param list<KeyPress|null> $script `null` udaje brak wejścia w danym odczycie
     * @param int|null            $shutdownAfterReads po ilu odczytach udać sygnał zamknięcia
     */
    public function __construct(array $script = [], ?int $shutdownAfterReads = null)
    {
        $this->script = $script;
        $this->shutdownAfterReads = $shutdownAfterReads ?? PHP_INT_MAX;
    }

    public function readKey(): ?KeyPress
    {
        ++$this->reads;

        if ($this->reads >= $this->shutdownAfterReads) {
            $this->shutdownRequested = true;
        }

        return array_shift($this->script);
    }

    public function shutdownRequested(): bool
    {
        return $this->shutdownRequested;
    }

    public function reads(): int
    {
        return $this->reads;
    }
}
