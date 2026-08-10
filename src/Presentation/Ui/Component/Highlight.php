<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Corner;
use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\Weight;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;

/**
 * Pasek pod wierszem, na którym stoi kursor.
 *
 * Wydzielony, bo używają go dwa komponenty — lista i pozycja ustawień — a
 * zaznaczenie musi w obu wyglądać identycznie. Do kroku 18 wyglądało, bo oba
 * ekrany sięgały po ten sam `LineStyle::Selected`; po rozdzieleniu komponentów
 * nic by tego już nie pilnowało poza uwagą piszącego.
 */
final class Highlight
{
    private function __construct()
    {
    }

    /** @return list<Primitive> */
    public static function under(Rect $line): array
    {
        return [
            new RoundRect($line, Role::Selection, null, Corner::Soft),
            new Bar($line, Role::Accent, Weight::Edge),
        ];
    }
}
