<?php

declare(strict_types=1);

namespace LightManager\Application\UseCase;

use LightManager\Domain\Aggregate\Directory;
use LightManager\Domain\Repository\DirectoryRepositoryInterface;

final class ToggleHiddenEntriesUseCase
{
    public function __construct(
        private readonly DirectoryRepositoryInterface $directories,
    ) {
    }

    /**
     * Odczytuje bieżący katalog ponownie, z nowym ustawieniem widoczności
     * wpisów ukrytych, i stara się zostawić zaznaczenie na tym samym wpisie.
     * Gdy wpis zniknął z listy — zaznaczenie wraca na jej początek.
     *
     * @throws \LightManager\Domain\Exception\DirectoryNotReadableException
     */
    public function execute(Directory $current, bool $includeHidden): Directory
    {
        $selectedName = $current->selectedEntry()?->name;

        $reloaded = $this->directories->get($current->path(), $includeHidden);

        if ($selectedName !== null) {
            $reloaded->selectEntryNamed($selectedName);
        }

        return $reloaded;
    }
}
