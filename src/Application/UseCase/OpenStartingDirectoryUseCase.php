<?php

declare(strict_types=1);

namespace LightManager\Application\UseCase;

use LightManager\Domain\Aggregate\Directory;
use LightManager\Domain\Exception\DirectoryNotReadableException;
use LightManager\Domain\Repository\DirectoryRepositoryInterface;
use LightManager\Domain\ValueObject\DirectoryPath;

final class OpenStartingDirectoryUseCase
{
    public function __construct(
        private readonly DirectoryRepositoryInterface $directories,
    ) {
    }

    /**
     * Otwiera katalog startowy, a gdy nie da się go odczytać — cofa się w górę
     * drzewa do pierwszego katalogu, który da się otworzyć. Aplikacja ma
     * wystartować także wtedy, gdy katalog roboczy zniknął albo stracił
     * uprawnienia.
     *
     * @throws DirectoryNotReadableException gdy nawet korzeń jest nieczytelny
     */
    public function execute(DirectoryPath $requested, bool $includeHidden): Directory
    {
        $candidate = $requested;

        while (true) {
            try {
                return $this->directories->get($candidate, $includeHidden);
            } catch (DirectoryNotReadableException $exception) {
                $parent = $candidate->parent();

                if ($parent === null) {
                    throw $exception;
                }

                $candidate = $parent;
            }
        }
    }
}
