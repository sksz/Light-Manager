<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Role;

/**
 * Jeden węzeł drzewa — **już spłaszczonego**, czyli wpisany w listę wierszy.
 *
 * Jest **daną, nie komponentem**, dokładnie tak samo jak `ListRow` z kroku 18
 * i `Section` z kroku 22. Powód jest ten sam, co przy sekcjach i nie jest wygodą:
 * drzewo przewija się **jak jedna lista**, więc wycinanie okna musi widzieć
 * wszystkie widoczne węzły naraz. Węzeł rysujący się sam znałby tylko swój
 * prostokąt i nie umiałby powiedzieć, że zaczyna się trzy wiersze nad górną
 * krawędzią okna.
 *
 * **Bez wskaźnika na rodzica i bez listy dzieci** — to jest główne rozstrzygnięcie
 * planu kroku 31 i wynika wprost z reguły 1 (D42): rdzeń nie wie, czym jest
 * katalog, a komponent schodzący sam po drzewie musiałby wiedzieć, skąd biorą się
 * dzieci. A dzieci biorą się z wejścia-wyjścia, którego rdzeń nie zna. Spłaszczenie
 * należy więc do modułu, tak jak w kroku 22 należało do ekranu.
 *
 * `key` jest **napisem, a nie numerem**, i jest to ta sama ochrona, co przy
 * `Section::$key`: rozwinięcie trzyma `TreeState` pod tym kluczem, a gałąź, która
 * zniknęła z listy i wróciła — bo użytkownik wszedł katalog wyżej i się rozmyślił
 * — ma wrócić rozwinięta. Numer po zmianie drzewa wskazywałby na sąsiada.
 *
 * `guides` niesie to, czego z samej głębokości wyczytać się nie da: **czy przodek
 * na danym poziomie ma jeszcze rodzeństwo pod spodem**. Bez tej odpowiedzi nie da
 * się narysować pionowej prowadnicy (`│`), bo poziom, na którym przodek był
 * ostatni, musi zostać pusty. Głębokość jest przez to długością tej listy, a nie
 * osobnym polem — dwa pola mówiące to samo rozjechałyby się przy pierwszym
 * spłaszczeniu liczonym inaczej.
 */
final readonly class TreeNode
{
    /**
     * @param list<bool> $guides dla każdego przodka po kolei: czy ma jeszcze
     *                           rodzeństwo poniżej — czyli czy na jego poziomie
     *                           biegnie pionowa prowadnica
     * @param bool $last         czy węzeł jest ostatnim dzieckiem swojego rodzica
     * @param bool $hasChildren  czy węzeł **może** mieć dzieci; katalog pustego
     *                           nie odróżnia się od niepustego, dopóki się go nie
     *                           przeczyta, więc znacznik dostają wszystkie
     * @param string $value      krótka wartość po prawej stronie wiersza, jak
     *                           `ListRow::$right`; pusta, gdy węzeł nic nie mówi
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $guides = [],
        public bool $last = true,
        public bool $hasChildren = false,
        public bool $expanded = false,
        public string $value = '',
        public Role $role = Role::Text,
    ) {
    }

    /** Ile poziomów nad węzłem — czyli ile prowadnic poprzedza jego znacznik. */
    public function depth(): int
    {
        return count($this->guides);
    }
}
