<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Domain\ValueObject\ScrollPosition;

/**
 * Okno przewijania: który wycinek listy jest widoczny i jak podąża za kursorem.
 *
 * Jedyna implementacja rachunku, który do kroku 18 stał w kodzie **trzy razy** —
 * `windowOffset()` z marginesem w przeglądarce, `offsetFor()` w ustawieniach i
 * `clamp()` w pomocy. Trzy warianty tej samej reguły, każdy z własnymi
 * granicami; rozjeżdżały się już przy trzecim.
 *
 * Okno żyje **między klatkami**, więc nie może być komponentem: komponent
 * powstaje na nowo trzydzieści razy na sekundę i nie zapamiętałby, gdzie stał
 * przed chwilą. Właścicielem jest ekran.
 */
final class ScrollWindow
{
    private int $offset = 0;

    private ?string $context = null;

    /**
     * @param int $margin ile wierszy zapasu zostawić między kursorem a krawędzią
     *                    okna; 0 znaczy „przesuwaj dopiero, gdy kursor wyjdzie”
     */
    public function __construct(
        private readonly int $margin = 0,
    ) {
    }

    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * Zmiana kontekstu zaczyna oglądanie od początku — wejście do innego
     * katalogu nie ma powodu zaczynać w połowie listy.
     */
    public function useContext(string $context): void
    {
        if ($this->context !== $context) {
            $this->context = $context;
            $this->offset = 0;
        }
    }

    /**
     * Przesuwa okno tak, żeby kursor był widoczny wraz z zapasem.
     *
     * Okno rusza dopiero wtedy, gdy kursor zbliży się do krawędzi na mniej niż
     * margines — dzięki temu lista nie skacze przy każdym ruchu.
     */
    public function keepVisible(?int $index, int $total, int $capacity): int
    {
        if ($capacity < 1 || $index === null || $total <= $capacity) {
            return $this->offset = 0;
        }

        $margin = min($this->margin, intdiv(max(0, $capacity - 1), 2));

        if ($index - $margin < $this->offset) {
            $this->offset = $index - $margin;
        }

        if ($index + $margin > $this->offset + $capacity - 1) {
            $this->offset = $index + $margin - $capacity + 1;
        }

        return $this->offset = max(0, min($this->offset, $total - $capacity));
    }

    /** Przewinięcie o zadaną liczbę wierszy — dolną granicę pilnujemy od razu. */
    public function scrollBy(int $delta): void
    {
        $this->offset = max(0, $this->offset + $delta);
    }

    /**
     * Ścina okno do granic listy. Górną granicę zna dopiero składanie klatki, bo
     * dopiero ono wie, ile wierszy ma panel i ile treści zostało do pokazania.
     */
    public function clamp(int $total, int $capacity): int
    {
        if ($capacity < 1 || $total <= $capacity) {
            return $this->offset = 0;
        }

        return $this->offset = max(0, min($this->offset, $total - $capacity));
    }

    /** Trzy liczby, z których renderer rysuje suwak; `null` — nie ma czego rysować. */
    public function position(int $total, int $capacity): ?ScrollPosition
    {
        if ($total === 0 || $capacity < 1) {
            return null;
        }

        return new ScrollPosition($this->offset, min($capacity, $total - $this->offset), $total);
    }
}
