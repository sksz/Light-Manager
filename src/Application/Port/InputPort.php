<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

use LightManager\Application\Dto\KeyPress;

/**
 * Źródło zdarzeń wejściowych pętli głównej — do kroku 34 wyłącznie terminal,
 * od kroku 34 także okno GLFW. Kontrakt od terminala nie zależał nigdy;
 * neutralną nazwę dostał wraz z drugą implementacją (D53).
 */
interface InputPort
{
    /**
     * Odczyt nieblokujący: `null` oznacza brak wejścia w tej iteracji pętli,
     * nie koniec strumienia. Wieloznakowa sekwencja escape (np. strzałka)
     * wraca jako jedno zdarzenie.
     */
    public function readKey(): ?KeyPress;

    /**
     * Czy proces dostał żądanie zamknięcia — sygnał (Ctrl+C, SIGTERM) albo
     * przycisk zamknięcia okna. Pętla ma wtedy wyjść tak samo, jak po klawiszu
     * wyjścia — przez `break`, a nie przez ubicie procesu w środku iteracji.
     */
    public function shutdownRequested(): bool;
}
