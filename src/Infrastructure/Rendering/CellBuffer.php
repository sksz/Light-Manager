<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Rendering;

use LightManager\Application\Ui\FrameText;
use LightManager\Application\Ui\Role;

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
 *
 * **Krok 56 zabrał stąd rozbiór prymitywów na komórki, a zostawił kolory.**
 * Siatkę znaków wraz z rolami buduje odtąd `Application\Ui\FrameText` — wspólny
 * rachunek trzech torów — a ta klasa jest tym, czym tryb tekstowy różni się od
 * pozostałych: **nakłada na role paletę i składa bajty**. Podział przechodzi
 * dokładnie tam, gdzie leży granica warstw: role są pojęciem `Application`,
 * kody ANSI — `Infrastructure`.
 */
final class CellBuffer
{
    /**
     * @param array<string, string> $colors rola → kolor motywu, pod kluczem
     *                                      `Role::$name`; mapę składa renderer
     *                                      raz na klatkę, bo tabela ról jest
     *                                      jego, a nie siatki znaków
     */
    public function __construct(
        private readonly FrameText $text,
        private readonly array $colors,
    ) {
    }

    /**
     * Klatka złożona w jeden napis z kodami ANSI.
     *
     * Kody wypisujemy tylko przy **zmianie** koloru, a nie przy każdej komórce:
     * wiersz listy o jednolitym kolorze daje wtedy jeden kod zamiast stu
     * sześćdziesięciu. Porównujemy przy tym **role, nie kolory** — dwie role
     * o tej samej wartości w palecie (w Grafitcie `Accent` i `Warning`) dają
     * przez to jeden kod więcej niż musiały; jest to cena mniejsza niż
     * porównywanie napisów w każdej komórce.
     */
    public function toAnsi(AnsiPalette $palette): string
    {
        $lines = [];

        for ($row = 0; $row < $this->text->rows; ++$row) {
            $glyphs = $this->text->glyphRow($row);
            $foregrounds = $this->text->foregroundRow($row);
            $backgrounds = $this->text->backgroundRow($row);

            $line = '';
            $currentForeground = null;
            $currentBackground = null;

            for ($column = 0; $column < $this->text->columns; ++$column) {
                $foreground = $foregrounds[$column] ?? null;
                $background = $backgrounds[$column] ?? null;

                if ($foreground !== $currentForeground || $background !== $currentBackground) {
                    $line .= AnsiPalette::RESET;
                    $line .= $this->code($palette, $background, foreground: false);
                    $line .= $this->code($palette, $foreground, foreground: true);
                    $currentForeground = $foreground;
                    $currentBackground = $background;
                }

                $line .= $glyphs[$column] ?? ' ';
            }

            $lines[] = $line . AnsiPalette::RESET;
        }

        return implode("\r\n", $lines);
    }

    /**
     * Kod koloru albo pusty napis — dla komórki bez roli i dla roli, której
     * wołający nie podał w mapie. Ta druga sytuacja nie zdarza się w aplikacji
     * (renderer składa mapę z `Role::cases()`), a milczenie jest tu lepsze od
     * wyjątku: brakująca rola zostawia komórkę w kolorze domyślnym terminala,
     * czyli klatkę czytelną zamiast przerwanego rysowania.
     */
    private function code(AnsiPalette $palette, ?Role $role, bool $foreground): string
    {
        $color = $role === null ? null : ($this->colors[$role->name] ?? null);

        if ($color === null) {
            return '';
        }

        return $foreground ? $palette->foreground($color) : $palette->background($color);
    }
}
