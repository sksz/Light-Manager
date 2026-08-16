<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

use LightManager\Application\Dto\InputEvent;

/**
 * Źródło zdarzeń wejściowych pętli głównej — do kroku 34 wyłącznie terminal,
 * od kroku 34 także okno GLFW. Kontrakt od terminala nie zależał nigdy;
 * neutralną nazwę dostał wraz z drugą implementacją (D53).
 *
 * Od kroku 55 port oddaje `InputEvent`, a nie `KeyPress`: wskaźnik stoi w
 * **tej samej kolejce**, co klawisze (D95 nr 1). Drugi kanał portu byłby
 * tańszy i został odrzucony z jednego powodu — kolejność kliknięcia wobec
 * klawisza rozstrzygałaby wtedy kolejność pytań w takcie, a nie kolejność
 * zdarzeń u użytkownika.
 */
interface InputPort
{
    /**
     * Odczyt nieblokujący: `null` oznacza brak wejścia w tej iteracji pętli,
     * nie koniec strumienia. Wieloznakowa sekwencja escape (np. strzałka albo
     * kliknięcie w trybie SGR) wraca jako jedno zdarzenie.
     */
    public function readEvent(): ?InputEvent;

    /**
     * Włącza albo zdejmuje raportowanie wskaźnika — **w locie** (krok 55,
     * D95 nr 10).
     *
     * Metoda stoi w porcie, a nie w usłudze konkretnego toru, bo raportowanie
     * jest własnością **źródła wejścia**, a nie terminala: okno GLFW nie ma
     * czego zdejmować, więc odpina zdarzenia od kolejki — i zachowanie ma być
     * przez to takie samo, a nie podobne. Pytanie pada raz na takt i jest tanie:
     * obie implementacje wychodzą od razu, gdy stan się nie zmienił.
     */
    public function useMouseReporting(bool $enabled): void;

    /**
     * Czy proces dostał żądanie zamknięcia — sygnał (Ctrl+C, SIGTERM) albo
     * przycisk zamknięcia okna. Pętla ma wtedy wyjść tak samo, jak po klawiszu
     * wyjścia — przez `break`, a nie przez ubicie procesu w środku iteracji.
     */
    public function shutdownRequested(): bool;
}
