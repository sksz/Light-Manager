<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Port\ThemePort;

/** Katalog motywów o z góry znanej zawartości — bez wartości kolorów. */
final class FixedThemes implements ThemePort
{
    /** @var list<string> */
    private readonly array $themes;

    /** @param list<string> $themes */
    public function __construct(array $themes = ['grafit', 'nordyk', 'papier', 'indygo'])
    {
        $this->themes = $themes;
    }

    /** @return list<string> */
    public function names(): array
    {
        return $this->themes;
    }

    public function has(string $name): bool
    {
        return in_array($name, $this->themes, true);
    }
}
