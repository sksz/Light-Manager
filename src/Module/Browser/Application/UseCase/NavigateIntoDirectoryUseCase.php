<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Application\UseCase;

use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\Repository\DirectoryRepositoryInterface;

final class NavigateIntoDirectoryUseCase
{
    public function __construct(
        private readonly DirectoryRepositoryInterface $directories,
    ) {
    }

    /**
     * Wchodzi do zaznaczonego katalogu. Zwraca `null`, gdy zaznaczenie nie
     * wskazuje katalogu (albo nie ma go wcale) — wtedy wołający decyduje, co
     * zrobić z wpisem, który katalogiem nie jest.
     *
     * @throws \LightManager\Module\Browser\Domain\Exception\DirectoryNotReadableException
     */
    public function execute(Directory $current, bool $includeHidden): ?Directory
    {
        $entry = $current->selectedEntry();

        if ($entry === null || !$entry->isDirectory()) {
            return null;
        }

        return $this->directories->get($current->path()->child($entry->name), $includeHidden);
    }
}
