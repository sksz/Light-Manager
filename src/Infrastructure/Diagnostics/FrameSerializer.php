<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Ui\Frame;

/**
 * Klatka spisana tekstem: płaszczyzny, ich prostokąty i podpisy prymitywów.
 *
 * Dwoje użytkowników i obaj potrzebują tego samego (krok 38): **komenda zrzutu**
 * z żywej aplikacji i **złote klatki** scenariuszy w testach. Format jest ten
 * sam, bo pytanie jest to samo — „co dokładnie znalazło się w tej klatce”.
 *
 * Podpis prymitywu bierze się wprost z `Primitive::signature()`, czyli z tego
 * samego napisu, którym renderery kluczują swoje pamięci podręczne. To nie jest
 * wygoda, tylko gwarancja: **nie istnieje zmiana wpływająca na piksele, która
 * omija podpis** (D34), więc nie istnieje też zmiana, którą ten zapis
 * przeoczy.
 *
 * Zapis jest nietłumaczony i techniczny — jak podpis konfiguracji wzorca (D33).
 * Czyta go człowiek szukający usterki i `diff` porównujący złoty plik.
 */
final class FrameSerializer
{
    public function toText(Frame $frame): string
    {
        $lines = [];

        foreach ($frame->planes as $plane) {
            $lines[] = sprintf(
                'plane %s rect=%d,%d %dx%d opaque=%d primitives=%d',
                $plane->id,
                $plane->bounds->row,
                $plane->bounds->column,
                $plane->bounds->rows,
                $plane->bounds->columns,
                $plane->opaque ? 1 : 0,
                count($plane->primitives),
            );

            foreach ($plane->primitives as $primitive) {
                $lines[] = '  ' . $primitive->signature();
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
