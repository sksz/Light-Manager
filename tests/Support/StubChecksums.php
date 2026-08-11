<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\FileInfo\Application\Dto\ChecksumState;
use LightManager\Module\FileInfo\Application\Port\ChecksumPort;

/**
 * Suma kontrolna bez pliku: kończy się po zadanej liczbie kroków.
 *
 * Prawdziwa usługa czyta plik po kawałku i test musiałby mieć plik dokładnie
 * takiej wielkości, żeby zobaczyć postęp pośredni. Tutaj liczba kroków jest
 * wprost powiedziana, więc da się sprawdzić to, co naprawdę jest do sprawdzenia:
 * **że praca posuwa się o jeden kawałek na klatkę i że da się ją przerwać**.
 */
final class StubChecksums implements ChecksumPort
{
    public const DIGEST = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    /** @var list<string> ścieżki, dla których zaczęto liczenie */
    public array $startedPaths = [];

    public int $stopCount = 0;

    private int $step = 0;

    private ChecksumState $state;

    public function __construct(
        private readonly int $steps = 4,
    ) {
        $this->state = ChecksumState::idle();
    }

    public function begin(string $path): ChecksumState
    {
        $this->startedPaths[] = $path;
        $this->step = 0;

        return $this->state = ChecksumState::running(0.0);
    }

    public function advance(int $bytes): ChecksumState
    {
        if (!$this->state->isRunning()) {
            return $this->state;
        }

        ++$this->step;

        return $this->state = $this->step >= $this->steps
            ? ChecksumState::done(self::DIGEST)
            : ChecksumState::running($this->step / $this->steps);
    }

    public function state(): ChecksumState
    {
        return $this->state;
    }

    public function stop(): void
    {
        ++$this->stopCount;
        $this->step = 0;
        $this->state = ChecksumState::idle();
    }
}
