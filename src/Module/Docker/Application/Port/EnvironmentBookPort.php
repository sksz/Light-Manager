<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Port;

use LightManager\Module\Docker\Application\EnvironmentBook;

/**
 * Zapis i odczyt książki środowisk w pliku stanu modułu (krok 58).
 *
 * Plik to `~/.light-manager/docker.json` — **plik stanu modułu, nie plik
 * książki**, dokładnie tak, jak `ssh.json` w kroku 48: krok 60 dopisze do tego
 * samego dokumentu książkę rejestrów **kluczami**, a nie drugim plikiem, więc
 * nieznane klucze mają przeżywać zapis od pierwszego dnia. Dwa niezależne
 * zapisy jednego pliku to wyścig przy pierwszym zapisie z dwóch miejsc.
 */
interface EnvironmentBookPort
{
    public function load(): LoadedEnvironmentBook;

    public function save(EnvironmentBook $book): void;

    /** Gdzie plik leży — ekran pokazuje to, gdy spis jest pusty. */
    public function location(): string;
}
