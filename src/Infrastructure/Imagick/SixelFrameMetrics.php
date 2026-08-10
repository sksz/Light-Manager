<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Imagick;

/**
 * Przelicza siatkę znakową terminala na piksele płótna.
 *
 * Wysokość wiersza bierze się z podziału płótna przez liczbę wierszy, więc
 * tekst rysowany przez Imagick pokrywa się z tym, co terminal uważa za wiersz —
 * bez tego lista policzona w warstwie aplikacji nie mieściłaby się w klatce.
 */
final class SixelFrameMetrics
{
    /** Udział wysokości wiersza przypadający na wysokość liter. */
    private const FONT_SIZE_RATIO = 0.78;

    /**
     * Promień zaokrąglenia jako udział wysokości wiersza. Stała w pikselach nie
     * zdałaby egzaminu: przy komórce 6×13 px promień 8 px zjadłby róg panelu, a
     * przy komórce dwukrotnie większej byłby ledwie widoczny.
     */
    private const CORNER_RADIUS_RATIO = 0.5;

    public readonly int $rowHeight;

    public readonly int $columnWidth;

    public readonly int $fontSize;

    public readonly int $cornerRadius;

    public function __construct(
        public readonly int $widthPixels,
        public readonly int $heightPixels,
        public readonly int $rows,
        public readonly int $columns,
    ) {
        $this->rowHeight = max(1, intdiv($heightPixels, max(1, $rows)));
        $this->columnWidth = max(1, intdiv($widthPixels, max(1, $columns)));
        $this->fontSize = max(6, (int) round($this->rowHeight * self::FONT_SIZE_RATIO));
        $this->cornerRadius = max(2, (int) round($this->rowHeight * self::CORNER_RADIUS_RATIO));
    }

    /** Linia bazowa tekstu w danym wierszu siatki. */
    public function baselineOf(int $row): int
    {
        return (int) round(($row + self::FONT_SIZE_RATIO) * $this->rowHeight);
    }

    /**
     * Linia bazowa liczona od górnej krawędzi wiersza — dla tekstu rysowanego
     * do własnej bitmapy, która nie wie, w którym wierszu wyląduje.
     *
     * Wynik zgadza się z `baselineOf()` co do piksela w każdym wierszu, bo
     * `$row * $rowHeight` jest liczbą całkowitą, więc zaokrąglenie sumy równa
     * się sumie z zaokrągloną częścią ułamkową.
     */
    public function baselineWithinRow(): int
    {
        return (int) round(self::FONT_SIZE_RATIO * $this->rowHeight);
    }

    /**
     * Wysokość bitmapy wiersza — z zapasem na ogonki liter (`g`, `j`, `y`).
     *
     * Bitmapa wysoka dokładnie na wiersz ucinałaby je, a rysowane wprost na
     * płótnie schodzą swobodnie poniżej. Nadmiar nachodzi na wiersz następny,
     * ale bitmapy są przezroczyste poza literami, więc kolejny wiersz go nie
     * zamazuje.
     */
    public function rowBitmapHeight(): int
    {
        return $this->rowHeight + max(2, intdiv($this->rowHeight, 3));
    }

    /** Górna krawędź wiersza siatki. */
    public function topOf(int $row): int
    {
        return $row * $this->rowHeight;
    }

    /**
     * Środek wiersza siatki. Obwódki paneli biegną właśnie tędy, a nie po
     * krawędzi wiersza: linia w środku zostawia oddech po obu stronach, więc
     * tekst sąsiedniego wiersza jej nie dotyka.
     */
    public function middleOf(int $row): int
    {
        return $row * $this->rowHeight + intdiv($this->rowHeight, 2);
    }

    /** Lewa krawędź kolumny siatki. */
    public function xOf(int $column): int
    {
        return $column * $this->columnWidth;
    }
}
