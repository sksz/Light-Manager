<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Port\CommandHistoryPort;

/**
 * Historia komend bez dysku — pamięta to, co dostała, i liczy zapisy.
 *
 * Liczba zapisów jest tu istotą testu: reguła „zapis po zapełnieniu bufora i przy
 * zamknięciu” daje się sprawdzić wyłącznie przez to, **ile razy** port został
 * wywołany.
 */
final class InMemoryCommandHistory implements CommandHistoryPort
{
    public int $saves = 0;

    /** @param list<string> $entries */
    public function __construct(
        public array $entries = [],
    ) {
    }

    public function load(): array
    {
        return $this->entries;
    }

    public function save(array $entries): void
    {
        $this->entries = $entries;
        ++$this->saves;
    }
}
