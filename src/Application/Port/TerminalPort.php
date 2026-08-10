<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

use LightManager\Application\Dto\KeyPress;

interface TerminalPort
{
    /**
     * Odczyt nieblokujący: `null` oznacza brak wejścia w tej iteracji pętli,
     * nie koniec strumienia. Wieloznakowa sekwencja escape (np. strzałka)
     * wraca jako jedno zdarzenie.
     */
    public function readKey(): ?KeyPress;

    /**
     * Czy proces dostał sygnał zamknięcia (Ctrl+C, SIGTERM). Pętla ma wtedy
     * wyjść tak samo, jak po klawiszu wyjścia — przez `break`, a nie przez
     * ubicie procesu w środku iteracji.
     */
    public function shutdownRequested(): bool;
}
