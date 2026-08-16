<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Overlay;

/**
 * Czym jest teraz okno spod `F12`: spisem czynności czy spisem źródeł danych
 * (krok 53, D92 nr 7).
 *
 * Dwa tryby jednego okna, a nie dwa okna — bo różnią się **rejestrem, z którego
 * czytają**, a nie niczym innym: ten sam wiersz wpisywania, ten sam parser, te
 * same podpowiedzi, ten sam scenariusz pomiarowy `command`. Osobne okno
 * kosztowałoby własny klawisz rdzenia (czyli trzy tory wejścia) albo własną
 * komendę otwierającą, i w obu wypadkach własny scenariusz pomiaru.
 *
 * Zdanie, które ten podział trzyma, jest tym samym, co przy rejestrach:
 * **komenda robi, kwerenda mówi.**
 */
enum CommandWindowMode
{
    case Commands;

    case Queries;

    public function other(): self
    {
        return $this === self::Commands ? self::Queries : self::Commands;
    }

    /** Klucz etykiety obwódki — okno mówi tytułem, w którym trybie stoi. */
    public function titleKey(): string
    {
        return $this === self::Commands ? 'layout.zone.command' : 'layout.zone.query';
    }
}
