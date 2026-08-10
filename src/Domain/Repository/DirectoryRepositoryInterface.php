<?php

declare(strict_types=1);

namespace LightManager\Domain\Repository;

use LightManager\Domain\Aggregate\Directory;
use LightManager\Domain\ValueObject\DirectoryPath;

interface DirectoryRepositoryInterface
{
    /**
     * Odczytuje katalog wraz z zawartością, posortowaną do postaci gotowej do
     * pokazania: najpierw katalogi, potem pliki, w obu grupach alfabetycznie.
     *
     * Wpisy ukryte są odfiltrowywane już tutaj, przy odczycie — przełączenie
     * ich widoczności oznacza ponowne odczytanie katalogu, przy okazji
     * odświeżając listę o zmiany na dysku.
     *
     * @throws \LightManager\Domain\Exception\DirectoryNotReadableException
     */
    public function get(DirectoryPath $path, bool $includeHidden): Directory;
}
