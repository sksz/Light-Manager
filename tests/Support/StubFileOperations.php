<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\RemovalStage;
use LightManager\Application\Dto\RemovalState;
use LightManager\Application\Port\FileOperationsPort;
use LightManager\Domain\Exception\FileOperationException;

/**
 * Czynności na plikach, które **dysku nie dotykają** (krok 41).
 *
 * Atrapa istnieje z jednego powodu i warto go zapisać: zestaw ekranów
 * (`ScreenFixture`) składa prawdziwą przeglądarkę na katalogach trzymanych
 * w pamięci, a jej ścieżki (`/home`, `/home/projekty`) **przypadkiem bywają
 * prawdziwe na maszynie, na której idą testy**. Prawdziwa usługa zapisu
 * podstawiona tam, gdzie sprawdza się coś zupełnie innego — na przykład czy
 * stopka obiecuje działający klawisz — mogłaby zajrzeć do cudzego katalogu
 * domowego. Testy, które sprawdzają **same operacje**, biorą prawdziwą usługę
 * i prawdziwy katalog tymczasowy; wszystkie pozostałe biorą to.
 *
 * Praca kawałkowa jest tu skrócona do tego, co da się sprawdzić bez plików:
 * liczenie kończy się po zadanej liczbie kroków, a usuwanie po tyluż.
 */
final class StubFileOperations implements FileOperationsPort
{
    /** @var list<string> ślad wywołań: `rename:/a/b→c`, `mkdir:/a/b`, `delete:/a/b` */
    public array $performed = [];

    /** Powód, którym każda czynność ma się skończyć — `null` znaczy „udaje się”. */
    public ?FileOperationException $failWith = null;

    private RemovalState $removal;

    private int $step = 0;

    private string $path = '';

    public function __construct(
        /** Ile wywołań `advanceRemoval()` ma zająć liczenie, a potem usuwanie. */
        private readonly int $steps = 1,
        /** Ile wpisów „znajduje” liczenie — liczba, którą pytanie ma podać. */
        private readonly int $entries = 1,
    ) {
        $this->removal = RemovalState::idle();
    }

    public function rename(string $path, string $newName): void
    {
        $this->performed[] = 'rename:' . $path . '→' . $newName;
        $this->fail();
    }

    public function createDirectory(string $path): void
    {
        $this->performed[] = 'mkdir:' . $path;
        $this->fail();
    }

    public function delete(string $path): void
    {
        $this->performed[] = 'delete:' . $path;
        $this->fail();
    }

    /**
     * Ścieżka zapamiętana jest **pierwsza**, bo to ona nazywa pracę w oknach;
     * ślad `scan:` niesie za to wszystkie, rozdzielone przecinkiem — testy
     * sprawdzają nim, że zbiór zaznaczonych doszedł do portu w całości (krok 43).
     */
    public function beginRemoval(array $paths): RemovalState
    {
        $this->path = $paths[0] ?? '';
        $this->step = 0;
        $this->performed[] = 'scan:' . implode(',', $paths);

        return $this->removal = $this->steps > 0
            ? RemovalState::scanning(1, basename($this->path))
            : RemovalState::ready($this->entries);
    }

    public function advanceRemoval(int $entries): RemovalState
    {
        if (!$this->removal->isRunning()) {
            return $this->removal;
        }

        ++$this->step;
        $running = $this->step < $this->steps;

        if ($this->removal->stage === RemovalStage::Scanning) {
            return $this->removal = $running
                ? RemovalState::scanning($this->step, basename($this->path))
                : RemovalState::ready($this->entries);
        }

        return $this->removal = $running
            ? RemovalState::deleting($this->step, $this->entries, basename($this->path))
            : RemovalState::done($this->entries);
    }

    public function confirmRemoval(): RemovalState
    {
        if ($this->removal->stage !== RemovalStage::Ready) {
            return $this->removal;
        }

        $this->performed[] = 'remove:' . $this->path;
        $this->step = 0;

        return $this->removal = $this->steps > 0
            ? RemovalState::deleting(0, $this->entries, basename($this->path))
            : RemovalState::done($this->entries);
    }

    public function removalState(): RemovalState
    {
        return $this->removal;
    }

    public function stopRemoval(): void
    {
        $this->performed[] = 'stop';
        $this->removal = RemovalState::idle();
    }

    private function fail(): void
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }
    }
}
