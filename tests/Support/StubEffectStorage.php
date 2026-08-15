<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Audio\Application\EffectAssignment;
use LightManager\Module\Audio\Application\EffectMap;
use LightManager\Module\Audio\Application\Port\EffectMapPort;

/**
 * Mapa przypisań w pamięci — nośnik dla testów, które nie mają prawa dotknąć
 * pliku w katalogu domowym (krok 46).
 *
 * Bliźniak `StubPlaylistStorage` i z tego samego powodu: test sprawdzający,
 * **co** moduł zapisuje, nie musi sprawdzać, **czym**. Zapisy zostają w `$saved`
 * jako pary „zdarzenie → ścieżka", bo tyle wystarcza, by zobaczyć, że mapa
 * naprawdę poszła na dysk — i ile razy.
 */
final class StubEffectStorage implements EffectMapPort
{
    /** @var list<array<string, string>> przypisania z każdego zapisu, w kolejności */
    public array $saved = [];

    public int $loads = 0;

    /** @param array<string, EffectAssignment> $assignments */
    public function __construct(
        private readonly array $assignments = [],
    ) {
    }

    public function loadEffects(): EffectMap
    {
        ++$this->loads;

        return new EffectMap($this->assignments);
    }

    public function saveEffects(EffectMap $effects): void
    {
        $paths = [];

        foreach ($effects->all() as $event => $assignment) {
            $paths[$event] = $assignment->path;
        }

        $this->saved[] = $paths;
    }
}
