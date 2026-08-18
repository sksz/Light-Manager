<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Port;

use LightManager\Module\Docker\Application\EnvironmentBook;

/**
 * Wynik odczytu książki środowisk (krok 58, wzorem `LoadedHostBook`).
 *
 * Port nie rzuca (reguła 8): plik nieczytelny wraca pustą książką z kluczem
 * powodu, a plik, którego jeszcze nie ma, — pustą książką bez powodu.
 */
final readonly class LoadedEnvironmentBook
{
    public function __construct(
        public EnvironmentBook $book,
        public ?string $problemKey = null,
    ) {
    }
}
