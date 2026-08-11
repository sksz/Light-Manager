<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Role;

/**
 * Jeden wiersz tabeli: komórki wedle kolumn i rola koloru.
 *
 * Bliźniak `ListRow` z kroku 18, z jedną różnicą: komórek jest tyle, ile kolumn,
 * zamiast dwóch pól o ustalonym znaczeniu. `ListRow` **zostaje na miejscu** i nie
 * zmienia ani litery — opis pliku to naprawdę etykieta i wartość, a nie tabela
 * o dwóch kolumnach, i zmuszanie sekcji z kroku 22 do myślenia kolumnami byłoby
 * nazwaniem rzeczy nie po imieniu.
 *
 * Komórka jest **napisem, nie komponentem**, i to jest granica tej klasy. Gdyby
 * była komponentem, tabela musiałaby dzielić szerokość między dzieci, które
 * potrafią sobie tę szerokość interpretować po swojemu — a wtedy kolumny
 * w kolejnych wierszach przestałyby się zgadzać. Wyrównanie kolumn w pionie jest
 * jedyną rzeczą, która odróżnia tabelę od listy napisów.
 */
final readonly class TableRow
{
    /** @param list<string> $cells treść komórek, w kolejności kolumn */
    public function __construct(
        public array $cells,
        public Role $role = Role::Text,
    ) {
    }
}
