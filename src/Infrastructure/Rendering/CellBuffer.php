<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Rendering;

/**
 * Siatka znaków wraz z kolorem pierwszego planu i tła — płótno trybu
 * tekstowego.
 *
 * Do kroku 18 renderer tekstowy składał klatkę z **napisów**: jeden napis na
 * wiersz, podmieniany w całości. Działało to, dopóki klatka była listą wierszy,
 * ale okno modalne wymagało już nadpisywania fragmentu wiersza kodami ANSI
 * w środku napisu — i właśnie tam obie implementacje okienka się rozjeżdżały.
 * Płaszczyzny nakładane na siebie bez bufora komórek nie dają się zrobić
 * w ogóle.
 */
final class CellBuffer
{
    /** @var array<int, array<int, string>> znak w każdej komórce */
    private array $glyphs = [];

    /** @var array<int, array<int, ?string>> kolor pierwszego planu, `null` — domyślny */
    private array $foreground = [];

    /** @var array<int, array<int, ?string>> kolor tła, `null` — przezroczyste */
    private array $background = [];

    public function __construct(
        private readonly int $rows,
        private readonly int $columns,
    ) {
        for ($row = 0; $row < $rows; ++$row) {
            $this->glyphs[$row] = array_fill(0, max(1, $columns), ' ');
            $this->foreground[$row] = array_fill(0, max(1, $columns), null);
            $this->background[$row] = array_fill(0, max(1, $columns), null);
        }
    }

    public function put(int $row, int $column, string $glyph, ?string $color = null): void
    {
        if ($row < 0 || $row >= $this->rows || $column < 0 || $column >= $this->columns) {
            return;
        }

        $this->glyphs[$row][$column] = $glyph;

        if ($color !== null) {
            $this->foreground[$row][$column] = $color;
        }
    }

    public function write(int $row, int $column, string $text, ?string $color = null): void
    {
        $length = mb_strlen($text);

        for ($offset = 0; $offset < $length; ++$offset) {
            $this->put($row, $column + $offset, mb_substr($text, $offset, 1), $color);
        }
    }

    /** Tło komórki; znak zostaje nietknięty, więc tekst położony wcześniej przetrwa. */
    public function paint(int $row, int $column, string $color): void
    {
        if ($row < 0 || $row >= $this->rows || $column < 0 || $column >= $this->columns) {
            return;
        }

        $this->background[$row][$column] = $color;
    }

    /**
     * Klatka złożona w jeden napis z kodami ANSI.
     *
     * Kody wypisujemy tylko przy **zmianie** koloru, a nie przy każdej komórce:
     * wiersz listy o jednolitym kolorze daje wtedy jeden kod zamiast stu
     * sześćdziesięciu.
     */
    public function toAnsi(AnsiPalette $palette): string
    {
        $lines = [];

        for ($row = 0; $row < $this->rows; ++$row) {
            $line = '';
            $currentForeground = null;
            $currentBackground = null;

            for ($column = 0; $column < $this->columns; ++$column) {
                $foreground = $this->foreground[$row][$column];
                $background = $this->background[$row][$column];

                if ($foreground !== $currentForeground || $background !== $currentBackground) {
                    $line .= AnsiPalette::RESET;
                    $line .= $background === null ? '' : $palette->background($background);
                    $line .= $foreground === null ? '' : $palette->foreground($foreground);
                    $currentForeground = $foreground;
                    $currentBackground = $background;
                }

                $line .= $this->glyphs[$row][$column];
            }

            $lines[] = $line . AnsiPalette::RESET;
        }

        return implode("\r\n", $lines);
    }
}
