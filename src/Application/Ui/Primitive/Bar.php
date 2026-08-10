<?php

declare(strict_types=1);

namespace LightManager\Application\Ui\Primitive;

use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;

/**
 * Kreska albo płaszczyzna o ostrych narożnikach — wszystko, czego nie trzeba
 * zaokrąglać.
 *
 * Grubość jest nazwana, nie podana w pikselach: komponent wie, że chce
 * przegrodę, a nie że chce jeden piksel. Przy `Hairline` i `Edge` kreska stoi
 * przy **lewej** krawędzi prostokąta, bo obaj dzisiejsi użytkownicy — przegroda
 * w pasku stanu i krawędź paska zaznaczenia — właśnie tam ją mają.
 */
final class Bar implements Primitive
{
    public function __construct(
        public readonly Rect $bounds,
        public readonly Role $role,
        public readonly Weight $weight = Weight::Fill,
    ) {
    }

    public function signature(): string
    {
        return 'B' . $this->bounds->signature() . ',' . $this->role->name . ',' . $this->weight->name;
    }
}
