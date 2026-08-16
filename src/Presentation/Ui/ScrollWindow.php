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
     * Czy okno zostało przesunięte **ręcznie** i ma przestać biec za kursorem
     * (krok 55).
     *
     * Powód jest wymierny i wyszedł z rozpoznania przed pierwszą linią kodu:
     * kółko ma przewijać **bez ruszania kursora**, a szesnaście paneli listowych
     * woła `keepVisible()` w każdym rysowaniu — więc okno przesunięte kółkiem
     * wracałoby do kursora w tej samej klatce, w której je przesunięto.
     * Odczepienie jest jedyną drogą, która to godzi, i jest zarazem tą, którą
     * chodzi każdy edytor: kursor wolno wyprowadzić poza widok, a pierwszy jego
     * ruch przywraca go na oczy.
     */
    private bool $detached = false;

    /**
     * Kursor widziany przy poprzednim rozliczeniu — po nim poznaje się, że
     * użytkownik ruszył go klawiszem, i przyczepia okno z powrotem.
     */
    private ?int $lastIndex = null;

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
            $this->detached = false;
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
        // Kursor, który się ruszył, przyczepia okno z powrotem. Porównanie
        // z poprzednim odczytem jest jedyną drogą, jaką ta klasa ma — o tym, że
        // ktoś nacisnął strzałkę, dowiaduje się wyłącznie po tym, że numer
        // przyszedł inny.
        if ($index !== $this->lastIndex) {
            $this->lastIndex = $index;
            $this->detached = false;
        }

        if ($this->detached) {
            return $this->clamp($total, $capacity);
        }

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

    /**
     * Przewinięcie o zadaną liczbę wierszy — dolną granicę pilnujemy od razu.
     *
     * **Odczepia okno od kursora** (krok 55): przewijanie ręczne, czy to
     * klawiszem, czy kółkiem, jest zdaniem „chcę patrzeć tutaj”, a nie „przenieś
     * kursor”. Przyczepienie wraca samo, gdy kursor się ruszy.
     */
    public function scrollBy(int $delta): void
    {
        $this->detached = true;
        $this->offset = max(0, $this->offset + $delta);
    }

    /** Czy okno stoi tam, gdzie je przesunięto, zamiast biec za kursorem. */
    public function isDetached(): bool
    {
        return $this->detached;
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
