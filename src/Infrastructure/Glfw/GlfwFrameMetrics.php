<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

use LightManager\Application\Ui\Corner;
use LightManager\Application\Ui\Rect;

/**
 * Przelicza siatkę znakową na piksele framebuffera — okienny odpowiednik
 * `SixelFrameMetrics` (krok 35). Wzorzec przeniesiony, nie kod współdzielony:
 * te same proporcje i te same reguły lustrzanego mapowania, żeby klatka
 * w oknie układała się jak klatka sixelowa co do zasady, nie przypadkiem.
 *
 * Rozmiar pisma jest udziałem wysokości wiersza (nie odwrotnie), więc siatka
 * o dowolnym podziale — także narzucona przez narzędzie pomiarowe — dostaje
 * tekst proporcjonalny do wiersza, dokładnie jak w torze sixelowym.
 */
final class GlfwFrameMetrics
{
    /** Udział wysokości wiersza przypadający na wysokość liter — jak w `SixelFrameMetrics`. */
    public const FONT_SIZE_RATIO = 0.78;

    private const CORNER_RADIUS_RATIO = 0.5;

    public readonly int $rowHeight;

    public readonly int $columnWidth;

    public readonly float $fontSize;

    public readonly int $cornerRadius;

    public function __construct(
        public readonly int $widthPixels,
        public readonly int $heightPixels,
        public readonly int $rows,
        public readonly int $columns,
    ) {
        $this->rowHeight = max(1, intdiv($heightPixels, max(1, $rows)));
        $this->columnWidth = max(1, intdiv($widthPixels, max(1, $columns)));
        $this->fontSize = max(6.0, round($this->rowHeight * self::FONT_SIZE_RATIO, 1));
        $this->cornerRadius = max(2, (int) round($this->rowHeight * self::CORNER_RADIUS_RATIO));
    }

    /** Linia bazowa tekstu w danym wierszu siatki. */
    public function baselineOf(int $row): float
    {
        return round(($row + self::FONT_SIZE_RATIO) * $this->rowHeight);
    }

    /** Górna krawędź wiersza siatki. */
    public function topOf(int $row): int
    {
        return $row * $this->rowHeight;
    }

    /** Środek wiersza siatki — tędy biegną obwódki paneli, z oddechem po obu stronach. */
    public function middleOf(int $row): int
    {
        return $row * $this->rowHeight + intdiv($this->rowHeight, 2);
    }

    /** Lewa krawędź kolumny siatki. */
    public function xOf(int $column): int
    {
        return $column * $this->columnWidth;
    }

    /**
     * Prawa krawędź prostokąta w pikselach — liczona **od prawej krawędzi
     * framebuffera**, lustrzanie wobec lewej, żeby kształt pełnej szerokości
     * miał jednakowe marginesy, gdy szerokość nie dzieli się przez kolumny.
     */
    public function rightOf(Rect $bounds): int
    {
        return $this->widthPixels
            - $this->xOf(max(0, $this->columns - 1 - $bounds->right()))
            - 1;
    }

    /** Promień nie przekracza połowy boku — łuki rogów nie mają prawa się spotkać. */
    public function radiusFor(Corner $corner, int $height, int $width): int
    {
        $wanted = match ($corner) {
            Corner::Round => $this->cornerRadius,
            Corner::Soft => max(2, intdiv($this->rowHeight, 3)),
        };

        return max(1, min($wanted, intdiv($height, 2), intdiv($width, 2)));
    }
}
