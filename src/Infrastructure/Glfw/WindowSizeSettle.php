<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

/**
 * Czekanie, aż zmiany rozmiaru okna się uspokoją (krok 37).
 *
 * Przeciągnięcie rogu okna sypie zdarzeniami dziesiątkami na sekundę, a każde
 * z nich opisuje rozmiar, którego użytkownik nie zamierzał zostawić. Zapis
 * ustawień przy każdym z nich znaczyłby tyle samo zapisów pliku — więc zamiast
 * zapisywać, odnotowujemy chwilę zmiany i pytamy w kolejnych taktach, czy od
 * ostatniej minęła już cisza.
 *
 * Klasa jest **czysta i bez GLFW**: zna wyłącznie czas podany z zewnątrz, więc
 * daje się sprawdzić bez otwartego okna — tą samą zasadą co `GlfwKeyMapper`
 * i `GlfwViewportService::cells()`. Zegar przychodzi z zewnątrz także dlatego,
 * że wołający i tak wie, która jest godzina (reguła 11b).
 */
final class WindowSizeSettle
{
    /**
     * Cisza, po której uznajemy zmianę za zakończoną.
     *
     * Pół sekundy jest dłuższe od przerwy między zdarzeniami przeciągania
     * (te idą co kilkanaście milisekund) i krótsze od chwili, po której
     * użytkownik zdąży zamknąć aplikację — a i tak zdąży, bo wyjście z pętli
     * jest drugą drogą do zapisu.
     */
    public const SETTLE_SECONDS = 0.5;

    private ?float $changedAt = null;

    public function noteChange(float $now): void
    {
        $this->changedAt = $now;
    }

    /** Czy jest zmiana, na którą ktoś jeszcze czeka. */
    public function pending(): bool
    {
        return $this->changedAt !== null;
    }

    /**
     * Czy zmiany się uspokoiły. Oddaje `true` **raz** — po nim czekanie zaczyna
     * się od nowa, więc powtórne pytanie w tym samym takcie nie zapisze drugi raz.
     */
    public function settled(float $now): bool
    {
        if ($this->changedAt === null || $now - $this->changedAt < self::SETTLE_SECONDS) {
            return false;
        }

        $this->changedAt = null;

        return true;
    }

    /**
     * Porzucenie czekania bez uznania zmiany — dla zmian rozmiaru, których
     * użytkownik nie wybierał: wejścia w pełny ekran i powrotu z niego.
     */
    public function forget(): void
    {
        $this->changedAt = null;
    }
}
