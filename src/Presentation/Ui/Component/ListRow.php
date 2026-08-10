<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Role;

/**
 * Jeden wiersz listy: treść po lewej, wartość po prawej, rola koloru.
 *
 * Następca enuma `LineStyle` z `Domain`. Enum miał cztery przypadki, z których
 * dwa („zaznaczony” i „zaznaczony katalog”) znaczyły na ekranie dokładnie to
 * samo — zaznaczenie przykrywało kolor katalogu. Rola koloru wystarcza, a
 * zaznaczenie jest sprawą listy, nie wiersza: to ona wie, który wiersz jest
 * pod kursorem, i to ona kładzie pod nim pasek.
 */
final class ListRow
{
    public function __construct(
        public readonly string $left,
        public readonly string $right = '',
        public readonly Role $role = Role::Text,
    ) {
    }
}
