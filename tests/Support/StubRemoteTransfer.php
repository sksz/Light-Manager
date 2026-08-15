<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\TransferChoice;
use LightManager\Module\Ssh\Application\Port\RemoteTransferPort;
use LightManager\Module\Ssh\Application\RemoteTransferItem;
use LightManager\Module\Ssh\Application\RemoteTransferState;
use LightManager\Module\Ssh\Application\TransferDirection;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;

/**
 * Przesył bez sieci i bez dysku (krok 50).
 *
 * Prawdziwą pracę — kolejkę, nazwy robocze, zatwierdzenie i sprzątanie —
 * sprawdza `RemoteTransferServiceTest` na atrapie procesu potomnego. Tutaj
 * chodzi o **łańcuch okien**: co widzi użytkownik, kiedy praca trwa, kiedy pyta
 * o zajętą nazwę i kiedy się kończy. Atrapa oddaje więc stany na żądanie testu,
 * a nie wedle własnego rachunku.
 */
final class StubRemoteTransfer implements RemoteTransferPort
{
    /** @var list<array{list<RemoteTransferItem>, string, TransferDirection, list<string>}> */
    public array $started = [];

    public int $stopCount = 0;

    /** @var list<array{TransferChoice, ?string}> */
    public array $answers = [];

    public int $advances = 0;

    private RemoteTransferState $state;

    /** @var list<RemoteTransferState> stany oddawane kolejnym wywołaniom `advance()` */
    private array $steps = [];

    public function __construct(private readonly ?RemoteTransferState $onBegin = null)
    {
        $this->state = RemoteTransferState::idle();
    }

    /** Kolejne stany, które praca ma oddać przy posuwaniu. */
    public function willStep(RemoteTransferState ...$states): self
    {
        $this->steps = array_values($states);

        return $this;
    }

    public function begin(
        HostProfile $host,
        array $items,
        string $target,
        TransferDirection $direction,
        array $occupied = [],
    ): RemoteTransferState {
        $this->started[] = [$items, $target, $direction, $occupied];

        return $this->state = $this->onBegin ?? RemoteTransferState::beginning($items);
    }

    public function advance(): RemoteTransferState
    {
        ++$this->advances;

        if ($this->steps !== []) {
            $this->state = array_shift($this->steps);
        }

        return $this->state;
    }

    public function resolve(TransferChoice $choice, ?string $newName = null): RemoteTransferState
    {
        $this->answers[] = [$choice, $newName];

        if ($this->steps !== []) {
            $this->state = array_shift($this->steps);
        }

        return $this->state;
    }

    public function state(): RemoteTransferState
    {
        return $this->state;
    }

    public function stop(): void
    {
        ++$this->stopCount;
        $this->state = RemoteTransferState::idle();
    }
}
