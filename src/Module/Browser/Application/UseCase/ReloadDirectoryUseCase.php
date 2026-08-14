<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Application\UseCase;

use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\Repository\DirectoryRepositoryInterface;

/**
 * Odczyt zastanego katalogu **jeszcze raz** — wraz z ustawieniem zaznaczenia.
 *
 * Powstał w kroku 20 jako `ToggleHiddenEntriesUseCase` i nazwa mówiła wtedy
 * prawdę: jedynym powodem, dla którego katalog czytano ponownie, było
 * przełączenie widoczności wpisów ukrytych. Krok 41 dołożył drugi powód —
 * **własną zmianę na dysku** — a dwa przypadki użycia robiące to samo
 * rozjechałyby się przy pierwszej poprawce (reguła z kroku 32). Klasa dostała
 * więc nazwę od tego, co robi, i jeden parametr więcej.
 *
 * Zaznaczenie: nazwa podana z zewnątrz albo — gdy jej nie ma — ta, która była
 * zaznaczona. Wpis, który zniknął z listy, sprowadza zaznaczenie na jej początek
 * i robi to sam `Directory`.
 */
final class ReloadDirectoryUseCase
{
    public function __construct(
        private readonly DirectoryRepositoryInterface $directories,
    ) {
    }

    /**
     * @param ?string $select nazwa, na której ma stanąć zaznaczenie; `null` znaczy
     *                        „ta, która jest zaznaczona teraz”
     *
     * @throws \LightManager\Module\Browser\Domain\Exception\DirectoryNotReadableException
     */
    public function execute(Directory $current, bool $includeHidden, ?string $select = null): Directory
    {
        $name = $select ?? $current->selectedEntry()?->name;

        $reloaded = $this->directories->get($current->path(), $includeHidden);

        if ($name !== null) {
            $reloaded->selectEntryNamed($name);
        }

        return $reloaded;
    }
}
