<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Docker\Application\ContextEntry;
use LightManager\Module\Docker\Application\Port\ContextCatalogPort;

/**
 * Konteksty klienta `docker` bez klienta (krok 58).
 *
 * Odpowiedź jest podana z góry i przychodzi po zadanej liczbie posunięć —
 * liczba posunięć jest miarą czasu, jak w `StubBackgroundProcess`. Domyślnie
 * zero kontekstów, czyli maszyna bez klienta: przypadek, który plan każe
 * traktować jako zwykły, nie awaryjny.
 */
final class StubContextCatalog implements ContextCatalogPort
{
    public int $refreshCount = 0;

    private bool $reading = false;

    private int $advances = 0;

    /** @var list<ContextEntry> */
    private array $delivered = [];

    public function __construct(
        /** @var list<ContextEntry> odpowiedź, która przyjdzie po odczycie */
        private readonly array $contexts = [],
        private readonly int $advancesUntilDone = 1,
        private readonly ?string $problemKey = null,
    ) {
    }

    public function refresh(): void
    {
        ++$this->refreshCount;
        $this->reading = true;
        $this->advances = 0;
    }

    public function advance(): void
    {
        if (!$this->reading) {
            return;
        }

        if (++$this->advances < $this->advancesUntilDone) {
            return;
        }

        $this->reading = false;

        if ($this->problemKey === null) {
            $this->delivered = $this->contexts;
        }
    }

    public function all(): array
    {
        return $this->delivered;
    }

    public function isReading(): bool
    {
        return $this->reading;
    }

    public function problemKey(): ?string
    {
        return $this->reading ? null : $this->problemKey;
    }
}
