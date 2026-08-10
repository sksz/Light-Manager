<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

/**
 * Komenda, która potrafi podpowiedzieć wartości swoich argumentów.
 *
 * Osobny interfejs, a nie metoda w `CommandInterface`, bo większość komend nie
 * ma czego podpowiadać — `core.settings` nie przyjmuje niczego. Kiedy rdzeń
 * pyta, rozstrzyga `SuggestionSource` przy argumencie: raz przy starcie albo
 * przy każdej zmianie przedrostka.
 */
interface SuggestsArguments
{
    /**
     * @param string $argument nazwa argumentu z deklaracji
     * @param string $prefix   to, co użytkownik zdążył wpisać
     *
     * @return list<string>
     */
    public function suggestions(string $argument, string $prefix): array;
}
