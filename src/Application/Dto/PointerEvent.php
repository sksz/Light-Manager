<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

use LightManager\Application\Ui\Rect;

/**
 * Zdarzenie wskaźnika w **siatce znakowej** (krok 55, D95 nr 1).
 *
 * Współrzędne są w komórkach, nie w pikselach, i to jest rozstrzygnięcie, a nie
 * wygoda: `Rect` jest jedynym układem współrzędnych, jaki znają komponenty
 * (krok 18), a piksele zaczynają się dopiero w rendererze. Zdarzenie niosące
 * piksele zmuszałoby **każdy ekran** do poznania metryki czcionki. Przeliczenie
 * należy więc do infrastruktury: w terminalu robi je protokół (SGR podaje
 * kolumnę i wiersz liczone od jedynki — parser odejmuje ją raz), w oknie —
 * `GlfwPointerMapper` metryką, którą `GlfwViewportService` liczy i tak.
 *
 * Modyfikatory są **tymi samymi trzema**, co w `KeyPress`, i to jedyne, co
 * reguła 11j o wskaźniku mówi. Jej treści właściwej — „`ctrl` i `alt` wyłącznie
 * przy literach, `shift` wyłącznie przy klawiszach nazwanych” — nie ma tu jak
 * zastosować, bo wskaźnik nie ma ani litery, ani nazwy. Protokół podaje wszystkie
 * trzy niezależnie i tak też tu stoją; odbiorcy w tym kroku nie mają, a wchodzą,
 * bo rozbiera je i tak parser, a krok 56 pyta o `Shift` przy zaznaczaniu.
 */
final class PointerEvent implements InputEvent
{
    /**
     * O ile wierszy przewija jeden obrót kółka.
     *
     * **Nie jest to liczba do przestawiania w ustawieniach**: tyle daje każdy
     * terminal i każda przeglądarka, a przewijanie o inną liczbę niż reszta
     * systemu jest dla ręki myląca.
     */
    private const SCROLL_ROWS = 3;

    /**
     * @param int $row    wiersz siatki znakowej, liczony od zera
     * @param int $column kolumna siatki znakowej, liczona od zera
     */
    public function __construct(
        public readonly int $row,
        public readonly int $column,
        public readonly PointerButton $button,
        public readonly PointerAction $action,
        public readonly bool $ctrl = false,
        public readonly bool $alt = false,
        public readonly bool $shift = false,
    ) {
    }

    public static function press(int $row, int $column, PointerButton $button = PointerButton::Left): self
    {
        return new self($row, $column, $button, PointerAction::Press);
    }

    public static function release(int $row, int $column, PointerButton $button = PointerButton::Left): self
    {
        return new self($row, $column, $button, PointerAction::Release);
    }

    public static function drag(int $row, int $column, PointerButton $button = PointerButton::Left): self
    {
        return new self($row, $column, $button, PointerAction::Drag);
    }

    public static function scroll(int $row, int $column, bool $up): self
    {
        return new self(
            $row,
            $column,
            PointerButton::Left,
            $up ? PointerAction::ScrollUp : PointerAction::ScrollDown,
        );
    }

    /** Czy zdarzenie jest obrotem kółka — pytanie pada w każdym ekranie, więc stoi raz tutaj. */
    public function isScroll(): bool
    {
        return $this->action === PointerAction::ScrollUp || $this->action === PointerAction::ScrollDown;
    }

    /**
     * O ile wierszy przewinąć: ujemnie w górę, dodatnio w dół. Zdarzenie inne
     * niż kółko daje zero, żeby wołający nie musiał pytać dwa razy.
     */
    public function scrollRows(): int
    {
        return match ($this->action) {
            PointerAction::ScrollUp => -self::SCROLL_ROWS,
            PointerAction::ScrollDown => self::SCROLL_ROWS,
            default => 0,
        };
    }

    /**
     * Czy wskazana komórka leży wewnątrz prostokąta.
     *
     * Metoda stoi tutaj, a nie w każdym ekranie z osobna, bo pytanie pada
     * w każdym z nich i jest zawsze tym samym rachunkiem. `Rect` wolno tu
     * wymienić, bo obie klasy leżą w warstwie `Application`, a współrzędne
     * zdarzenia są **z tej samej siatki**, co prostokąt komponentu — inaczej
     * porównanie nie miałoby sensu.
     */
    public function hits(Rect $bounds): bool
    {
        return !$bounds->isEmpty()
            && $this->row >= $bounds->row
            && $this->row <= $bounds->bottom()
            && $this->column >= $bounds->column
            && $this->column <= $bounds->right();
    }

    /** Który wiersz prostokąta wskazano, licząc od jego góry; `null` — kliknięto poza nim. */
    public function rowIn(Rect $bounds): ?int
    {
        return $this->hits($bounds) ? $this->row - $bounds->row : null;
    }

    public function equals(self $other): bool
    {
        return $this->row === $other->row
            && $this->column === $other->column
            && $this->button === $other->button
            && $this->action === $other->action
            && $this->ctrl === $other->ctrl
            && $this->alt === $other->alt
            && $this->shift === $other->shift;
    }
}
