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
 *
 * Od kroku 30 wiersz niesie ponadto **zakresy dopasowania** — i niesie właśnie
 * zakresy, a nie podzieloną na kawałki treść. Podział wymagałby wiedzy, której
 * wiersz nie ma: od której kolumny zaczyna się napis i ile z niego zostanie po
 * przycięciu. Pusta tablica znaczy „nic nie jest podświetlone” i jest
 * **wartością domyślną**, więc wiersz bez dopasowania kosztuje dokładnie tyle,
 * co przed tamtym krokiem.
 */
final readonly class TableRow
{
    /**
     * @param list<string>                $cells treść komórek, w kolejności kolumn
     * @param array<int, list<TextSpan>>  $marks zakresy dopasowania wedle numeru
     *                                           kolumny; komórki bez dopasowania
     *                                           klucza nie mają
     */
    public function __construct(
        public array $cells,
        public Role $role = Role::Text,
        public array $marks = [],
    ) {
    }
}
