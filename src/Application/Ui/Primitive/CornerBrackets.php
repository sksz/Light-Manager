<?php

declare(strict_types=1);

namespace LightManager\Application\Ui\Primitive;

use LightManager\Application\Ui\Corner;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;

/**
 * Nawiasy narożne — dwie krótkie nogi wraz z łukiem między nimi, na dwóch
 * przeciwległych rogach prostokąta.
 *
 * Osobny prymityw, a nie ozdoba obwódki, bo **łuk należy do nawiasu**, nie do
 * ramki: rysowany kolorem obwódki po prostu ginie w terminalu, a akcent jest
 * jedynym kolorem, który czyta się od razu (krok 13, D27). Nawias jest też
 * grubszy od obwódki — to on niesie kształt narożnika.
 *
 * Prostokąt i zaokrąglenie muszą się zgadzać z towarzyszącym `RoundRect`,
 * inaczej łuk nawiasu rozjedzie się z łukiem ramki.
 */
final class CornerBrackets implements Primitive
{
    public function __construct(
        public readonly Rect $bounds,
        public readonly Role $role,
        public readonly Corner $corner = Corner::Round,
    ) {
    }

    public function signature(): string
    {
        return 'C' . $this->bounds->signature() . ',' . $this->role->name . ',' . $this->corner->name;
    }
}
