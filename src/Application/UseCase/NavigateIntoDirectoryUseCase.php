<?php

declare(strict_types=1);

namespace LightManager\Application\UseCase;

use LightManager\Domain\Aggregate\Directory;
use LightManager\Domain\Repository\DirectoryRepositoryInterface;

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
     * @throws \LightManager\Domain\Exception\DirectoryNotReadableException
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
