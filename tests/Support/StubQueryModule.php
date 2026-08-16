<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\ProvidesQueries;
use LightManager\Application\Query\QueryInterface;

/**
 * Moduł-atrapa wnoszący same kwerendy (krok 53).
 *
 * Pusta lista znaczy „moduł zdolność deklaruje, ale niczego nie wnosi” i jest
 * legalna — tak samo, jak moduł bez ani jednej zdolności.
 */
final class StubQueryModule implements ModuleInterface, ProvidesQueries
{
    /** @param list<QueryInterface> $queries */
    public function __construct(
        private readonly string $id,
        private readonly array $queries,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function nameKey(): string
    {
        return 'module.' . $this->id . '.name';
    }

    public function descriptionKey(): string
    {
        return 'module.' . $this->id . '.description';
    }

    public function shortcut(): ?ModuleShortcut
    {
        return null;
    }

    public function translations(): ?string
    {
        return null;
    }

    /** @return list<QueryInterface> */
    public function queries(): array
    {
        return $this->queries;
    }
}
