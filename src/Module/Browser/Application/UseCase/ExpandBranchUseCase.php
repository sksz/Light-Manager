<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Application\UseCase;

use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\Exception\DirectoryNotReadableException;
use LightManager\Module\Browser\Domain\Repository\DirectoryRepositoryInterface;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;

/**
 * Odczyt jednej gałęzi drzewa — czyli zawartości jednego katalogu, **na żądanie**.
 *
 * Osobny przypadek użycia, a nie `NavigateIntoDirectoryUseCase`, bo pytanie jest
 * inne: tamten wchodzi do katalogu **zaznaczonego w innym katalogu** i oddaje
 * `null`, gdy zaznaczenie katalogiem nie jest. Drzewo pyta o wskazaną ścieżkę
 * i zna odpowiedź na pytanie o rodzaj wpisu, zanim zapyta.
 *
 * **Gałąź nieczytelna nie jest wyjątkiem, tylko pustką.** To jest jedyna decyzja
 * tej klasy i wynika z tego, jak drzewo jest oglądane: rozwinięcie katalogu bez
 * prawa wejścia zdarza się w `/proc` i w cudzych katalogach domowych przy zwykłym
 * przewijaniu strzałką, a wyjątek zamieniałby każde takie potknięcie w komunikat
 * przykrywający pasek stanu. Gałąź rozwija się wtedy pusta — dokładnie tak, jak
 * wygląda katalog, do którego nie wolno zajrzeć.
 */
final class ExpandBranchUseCase
{
    public function __construct(
        private readonly DirectoryRepositoryInterface $directories,
    ) {
    }

    /** Zawartość gałęzi; katalog nieczytelny oddaje **pustą**, a nie wyjątek. */
    public function execute(DirectoryPath $path, bool $includeHidden): Directory
    {
        try {
            return $this->directories->get($path, $includeHidden);
        } catch (DirectoryNotReadableException) {
            return new Directory($path, []);
        }
    }
}
