<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

/**
 * Trwałe miejsce na wpisane komendy.
 *
 * Port **nie rzuca**: historia jest śladem pracy, nie danymi użytkownika, więc
 * nieudany odczyt oddaje pustą listę, a nieudany zapis milczy. Awaria historii
 * nie ma prawa przerwać ani startu, ani wyjścia z aplikacji (zasada z kroku 14:
 * wyjątek infrastruktury nie przekracza granicy portu).
 */
interface CommandHistoryPort
{
    /** @return list<string> wpisy od najstarszego */
    public function load(): array;

    /** @param list<string> $entries wpisy od najstarszego */
    public function save(array $entries): void;
}
