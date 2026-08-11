<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Application\UseCase;

use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\Repository\DirectoryRepositoryInterface;

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
     * @throws \LightManager\Module\Browser\Domain\Exception\DirectoryNotReadableException
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
