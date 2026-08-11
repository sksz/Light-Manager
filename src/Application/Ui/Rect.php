<?php

declare(strict_types=1);

namespace LightManager\Application\Ui;

/**
 * Prostokąt w siatce znakowej terminala — jedyny układ współrzędnych, jakim
 * posługują się komponenty.
 *
 * Piksele zaczynają się dopiero w rendererze: to on wie, ile ma wiersz i ile
 * kolumna. Bez tego rozdziału tryb tekstowy nie miałby jak narysować niczego,
 * co powstało z myślą o Sixelu — a od kroku 07 oba tryby muszą pokazywać tę
 * samą klatkę.
 *
 * Prostokąt bywa pusty (zero wierszy albo kolumn) i to nie jest błąd: tak
 * kontener mówi dziecku, że w tym oknie się nie zmieściło. Komponent, który
 * dostał pusty prostokąt, nie rysuje nic.
 */
final class Rect
{
    public function __construct(
        public readonly int $row,
        public readonly int $column,
        public readonly int $rows,
        public readonly int $columns,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->rows < 1 || $this->columns < 1;
    }

    /** Ostatni wiersz prostokąta; przy pustym — wiersz przed jego początkiem. */
    public function bottom(): int
    {
        return $this->row + $this->rows - 1;
    }

    /** Ostatnia kolumna prostokąta. */
    public function right(): int
    {
        return $this->column + $this->columns - 1;
    }

    /**
     * Prostokąt wsunięty do środka o podaną liczbę wierszy i kolumn z każdej
     * strony — wnętrze panelu wobec jego obwódki.
     */
    public function inset(int $rows, int $columns): self
    {
        return new self(
            $this->row + $rows,
            $this->column + $columns,
            max(0, $this->rows - 2 * $rows),
            max(0, $this->columns - 2 * $columns),
        );
    }

    /** Ten sam prostokąt przesunięty w dół i skrócony — kolejne wiersze treści. */
    public function rowsFrom(int $offset, int $rows): self
    {
        return new self(
            $this->row + $offset,
            $this->column,
            max(0, min($rows, $this->rows - $offset)),
            $this->columns,
        );
    }

    /** Pojedynczy wiersz prostokąta, liczony od jego góry. */
    public function line(int $offset): self
    {
        return $this->rowsFrom($offset, 1);
    }

    /**
     * To samo w poziomie: prostokąt przesunięty w prawo i zwężony.
     *
     * Bliźniak `rowsFrom()`, dopisany w kroku 27 — kolumna tabeli jest pierwszym
     * miejscem, w którym prostokąt trzeba pociąć wzdłuż drugiej osi. Że powstał
     * dopiero teraz, mówi coś o samej aplikacji: do kroku 27 wszystko dzieliło
     * się w pionie, a jedyny podział poziomy (krok 24) liczył połówki własnym
     * rachunkiem, bo połówki są dwie i znane z góry.
     */
    public function columnsFrom(int $offset, int $columns): self
    {
        return new self(
            $this->row,
            $this->column + $offset,
            $this->rows,
            max(0, min($columns, $this->columns - $offset)),
        );
    }

    public function equals(self $other): bool
    {
        return $this->row === $other->row
            && $this->column === $other->column
            && $this->rows === $other->rows
            && $this->columns === $other->columns;
    }

    public function signature(): string
    {
        return $this->row . ',' . $this->column . ',' . $this->rows . ',' . $this->columns;
    }
}
