<?php

declare(strict_types=1);

namespace LightManager\Application\Ui\Primitive;

use LightManager\Application\Ui\Corner;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;

/**
 * Prostokąt o zaokrąglonych narożnikach — wypełniony, obrysowany albo jedno
 * i drugie.
 *
 * Wypełnienie i obrys zajmują w siatce **różne miejsca**, i to nie jest
 * niedopatrzenie, tylko odwzorowanie tego, jak klatka wygląda od kroku 13:
 *
 * - **wypełnienie** pokrywa pełne komórki prostokąta (pasek zaznaczenia sięga
 *   krawędzi wiersza),
 * - **obrys** biegnie środkiem pierwszego i ostatniego wiersza (obwódka panelu
 *   zostawia oddech po obu stronach, więc tekst sąsiedniego wiersza jej nie
 *   dotyka — patrz `SixelFrameMetrics::middleOf()`).
 *
 * Dzięki temu panel i pasek zaznaczenia są tym samym prymitywem, mimo że na
 * ekranie zachowują się inaczej.
 */
final class RoundRect implements Primitive
{
    public function __construct(
        public readonly Rect $bounds,
        public readonly ?Role $fill,
        public readonly ?Role $stroke,
        public readonly Corner $corner = Corner::Round,
    ) {
    }

    public function signature(): string
    {
        return 'R' . $this->bounds->signature()
            . ',' . ($this->fill === null ? '-' : $this->fill->name)
            . ',' . ($this->stroke === null ? '-' : $this->stroke->name)
            . ',' . $this->corner->name;
    }
}
