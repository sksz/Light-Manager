<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

/**
 * Rozmiar okna terminala w dwóch jednostkach naraz: piksele (potrzebne
 * rendererowi Sixel) i komórki znakowe (potrzebne rendererowi tekstowemu).
 */
final class TerminalSize
{
    public function __construct(
        public readonly int $widthPixels,
        public readonly int $heightPixels,
        public readonly int $columns,
        public readonly int $rows,
    ) {
    }

    /** Porównanie w obu jednostkach naraz — renderer po tym poznaje, że okno się zmieniło. */
    public function equals(self $other): bool
    {
        return $this->widthPixels === $other->widthPixels
            && $this->heightPixels === $other->heightPixels
            && $this->columns === $other->columns
            && $this->rows === $other->rows;
    }

    /**
     * Wysokość obrazu pokrywającego okno, pomniejszona o jeden wiersz znakowy.
     *
     * Terminal po wyrysowaniu obrazu Sixel przesuwa kursor pod obraz. Obraz
     * sięgający ostatniego wiersza nie ma już gdzie tego kursora postawić, więc
     * ekran zostaje wypchnięty o wiersz w górę i pierwszy wiersz klatki —
     * nagłówek ze ścieżką — wyjeżdża za krawędź. Rezerwa jednego wiersza jest
     * tańsza niż zmiana trybów terminala: nie wymaga niczego poza arytmetyką i
     * działa jednakowo na każdym terminalu z Sixelem.
     */
    public function heightPixelsWithoutBottomRow(): int
    {
        return max(1, $this->heightPixels - $this->rowHeightPixels());
    }

    private function rowHeightPixels(): int
    {
        return max(1, intdiv($this->heightPixels, max(1, $this->rows)));
    }
}
