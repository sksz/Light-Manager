<?php

declare(strict_types=1);

namespace LightManager\Application\Ui\Primitive;

use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\ScrollPosition;

/**
 * Szyna z suwakiem pokazująca położenie okna przewijania.
 *
 * Osobny prymityw, bo suwak jest **węższy od komórki** i jego proporcje zależą
 * od danych, a nie od układu: wysokość uchwytu bierze się z tego, jaka część
 * treści jest widoczna. Złożenie tego z dwóch prostokątów wymagałoby od
 * komponentu rachunku w pikselach, których nie zna — a od renderera i tak nie
 * zdejmowałoby ani jednej decyzji.
 *
 * Tryb tekstowy pomija go w całości, tak samo jak pomijał do tej pory: w klatce
 * znakowej pół kolumny nie istnieje.
 */
final class Scrollbar implements Primitive
{
    public function __construct(
        public readonly Rect $bounds,
        public readonly ScrollPosition $position,
        public readonly Role $rail = Role::Border,
        public readonly Role $thumb = Role::Accent,
    ) {
    }

    public function signature(): string
    {
        return 'S' . $this->bounds->signature()
            . ',' . $this->position->first
            . ',' . $this->position->visible
            . ',' . $this->position->total
            . ',' . $this->rail->name . ',' . $this->thumb->name;
    }
}
