<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\InputEvent;
use LightManager\Application\Port\InputPort;

/**
 * Terminal sterowany scenariuszem: oddaje z góry zadane zdarzenia, a potem
 * `null`. Pozwala przetestować pętlę bez dotykania prawdziwego terminala.
 *
 * Od kroku 55 zdarzeniem bywa też `PointerEvent` — scenariusz miesza je
 * z klawiszami dokładnie tak, jak miesza je jedna kolejka portu.
 */
final class ScriptedTerminal implements InputPort
{
    /** @var list<InputEvent|null> */
    private array $script;

    private bool $shutdownRequested = false;

    private bool $mouseReporting = true;

    private int $shutdownAfterReads;

    private int $reads = 0;

    /**
     * @param list<InputEvent|null> $script `null` udaje brak wejścia w danym odczycie
     * @param int|null              $shutdownAfterReads po ilu odczytach udać sygnał zamknięcia
     */
    public function __construct(array $script = [], ?int $shutdownAfterReads = null)
    {
        $this->script = $script;
        $this->shutdownAfterReads = $shutdownAfterReads ?? PHP_INT_MAX;
    }

    public function readEvent(): ?InputEvent
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

    /** Scenariusz podaje zdarzenia wprost, więc raportowania nie ma czym włączyć ani zdjąć. */
    public function useMouseReporting(bool $enabled): void
    {
        $this->mouseReporting = $enabled;
    }

    /** Czy pętla poprosiła o raportowanie wskaźnika — do sprawdzenia w przebiegu. */
    public function reportsMouse(): bool
    {
        return $this->mouseReporting;
    }

    public function reads(): int
    {
        return $this->reads;
    }
}
