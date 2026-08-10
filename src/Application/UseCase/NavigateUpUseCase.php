<?php

declare(strict_types=1);

namespace LightManager\Application\UseCase;

use LightManager\Domain\Aggregate\Directory;
use LightManager\Domain\Repository\DirectoryRepositoryInterface;

final class NavigateUpUseCase
{
    public function __construct(
        private readonly DirectoryRepositoryInterface $directories,
    ) {
    }

    /**
     * Wychodzi do katalogu nadrzędnego i zaznacza w nim ten katalog, z którego
     * przyszliśmy — dzięki temu wchodzenie i wychodzenie nie gubi miejsca w
     * drzewie. Zwraca `null`, gdy jesteśmy już w korzeniu.
     *
     * @throws \LightManager\Domain\Exception\DirectoryNotReadableException
     */
    public function execute(Directory $current, bool $includeHidden): ?Directory
    {
        $parentPath = $current->path()->parent();

        if ($parentPath === null) {
            return null;
        }

        $parent = $this->directories->get($parentPath, $includeHidden);
        $parent->selectEntryNamed($current->path()->name());

        return $parent;
    }
}
