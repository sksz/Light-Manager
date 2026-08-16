<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Pokwitowanie pytania zadanego demonowi — tożsamość, nie połączenie (krok 51).
 *
 * Powtarza rolę `BackgroundHandle` z kroku 26 i powtarza ją świadomie: pytań do
 * demona bywa **kilka naraz** (lista kontenerów, lista obrazów, płynące logi),
 * więc odpowiedź musi dać się przypisać do pytania. Bez uchwytu ekran pokazałby
 * logi w miejscu listy obrazów przy pierwszym wyścigu.
 *
 * Regułą 15e to nie jest złamane: powtórzeniem objęte jest **pojęcie dziedziny**
 * (tożsamość rozmowy), a nie mechanizm rdzenia — pracy tłowej moduł nie
 * podrabia, tylko po nią sięga tam, gdzie jej potrzebuje (compose).
 */
final readonly class DockerCall
{
    public function __construct(public int $id)
    {
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }
}
