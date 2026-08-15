<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\TransferChoice;
use LightManager\Application\Dto\TransferStage;
use LightManager\Application\Dto\TransferState;
use LightManager\Application\Port\FileTransferPort;

/**
 * Kopiowanie, które **dysku nie dotyka** (krok 42).
 *
 * Istnieje z tego samego powodu, co `StubFileOperations`: zestaw ekranów składa
 * prawdziwą przeglądarkę na katalogach trzymanych w pamięci, a jej ścieżki bywają
 * na maszynie testowej prawdziwe — więc usługa naprawdę pisząca po dysku,
 * podstawiona tam, gdzie sprawdza się coś zupełnie innego, mogłaby napisać
 * w cudzym katalogu domowym. Testy samego kopiowania biorą prawdziwą usługę
 * i prawdziwy katalog tymczasowy.
 *
 * Praca jest skrócona do tego, co da się sprawdzić bez plików: liczenie zajmuje
 * zadaną liczbę kroków, potem tyleż zajmuje kopiowanie, a kolizję można zamówić
 * z góry.
 */
final class StubFileTransfers implements FileTransferPort
{
    /** @var list<string> ślad wywołań: `begin:/a→/b`, `advance`, `resolve:Skip`, `stop` */
    public array $performed = [];

    private TransferState $state;

    private int $step = 0;

    private string $name = '';

    public function __construct(
        /** Ile wywołań `advance()` ma zająć liczenie, a potem kopiowanie. */
        private readonly int $steps = 1,
        /** Ile wpisów i bajtów „znajduje” liczenie. */
        private readonly int $entries = 1,
        private readonly int $bytes = 1024,
        /** Czy po pierwszym kawałku kopiowania praca ma stanąć na kolizji. */
        private readonly bool $collides = false,
    ) {
        $this->state = TransferState::idle();
    }

    public function begin(array $sources, string $target, bool $move, array $targetNames = []): TransferState
    {
        $this->name = basename($sources[0] ?? '');
        $this->step = 0;
        // Nazwy docelowe (krok 44) wchodzą do śladu tylko wtedy, gdy są — żeby
        // dotychczasowe przebiegi porównujące ślad zostały bajt w bajt.
        $renames = $targetNames === [] ? '' : ' jako ' . implode(',', $targetNames);
        $this->performed[] = ($move ? 'move:' : 'copy:') . implode(',', $sources) . '→' . $target . $renames;

        return $this->state = $this->steps > 0
            ? TransferState::scanning(1, 0, $this->name)
            : TransferState::working($this->name, 0, $this->bytes, 0, $this->entries);
    }

    public function advance(int $budget): TransferState
    {
        if (!$this->state->isRunning()) {
            return $this->state;
        }

        $this->performed[] = 'advance';
        ++$this->step;
        $running = $this->step < $this->steps;

        if ($this->state->stage === TransferStage::Scanning) {
            return $this->state = $running
                ? TransferState::scanning($this->step, $this->step, $this->name)
                : TransferState::working($this->name, 0, $this->bytes, 0, $this->entries);
        }

        if ($this->collides) {
            return $this->state = TransferState::colliding($this->name, 0, $this->bytes, 0, $this->entries);
        }

        return $this->state = $running
            ? TransferState::working($this->name, $this->step, $this->bytes, 0, $this->entries)
            : TransferState::done($this->bytes, $this->bytes, $this->entries, $this->entries);
    }

    public function resolve(TransferChoice $choice, ?string $newName = null): TransferState
    {
        $this->performed[] = 'resolve:' . $choice->name . ($newName === null ? '' : ':' . $newName);

        if ($choice === TransferChoice::Abort) {
            return $this->state = TransferState::done(0, $this->bytes, 0, $this->entries);
        }

        return $this->state = TransferState::done($this->bytes, $this->bytes, $this->entries, $this->entries);
    }

    public function state(): TransferState
    {
        return $this->state;
    }

    public function stop(): void
    {
        $this->performed[] = 'stop';
        $this->state = TransferState::idle();
    }
}
