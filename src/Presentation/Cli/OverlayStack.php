<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Application\Event\AppEvent;
use LightManager\Application\Event\EventRegistry;
use LightManager\Presentation\Ui\OverlayInterface;
use LightManager\Presentation\Ui\Resettable;

/**
 * Które okno nakładane stoi nad ekranem.
 *
 * Stos ma dziś **jedno piętro** i klasa mówi to wprost, tak samo jak
 * `ScreenStack` mówi o swoich dwóch. Istnieje nie dlatego, że okien jest wiele,
 * lecz dlatego, że reguła „klawisz idzie najpierw na wierzch” ma mieć jedno
 * miejsce — do kroku 19 mieszkała w polu `LoopState` i w warunku wewnątrz
 * `InputHandler`, a każde nowe okno dopisywałoby do obu.
 */
final class OverlayStack
{
    private ?OverlayInterface $current = null;

    /**
     * @param ?EventRegistry $events `null` znaczy „nikomu nie ogłaszaj" i jest
     *                               dla testów, które składają stos same; w
     *                               aplikacji rejestr podaje `LoopState`
     */
    public function __construct(
        private readonly ?EventRegistry $events = null,
    ) {
    }

    public function current(): ?OverlayInterface
    {
        return $this->current;
    }

    public function isOpen(): bool
    {
        return $this->current !== null;
    }

    /**
     * Okno staje nad ekranem — i to jest drugi z trzech momentów, które rdzeń
     * ogłasza (krok 46).
     *
     * Zamknięcia okna **nie ogłaszamy** i nie jest to przeoczenie: okno zamyka się
     * na kilka sposobów naraz (`Esc`, wykonanie, ustąpienie miejsca innemu oknu
     * przez `OverlayOutcome::replace()`), a zdarzenie padające przy każdym z nich
     * znaczyłoby raz „zrezygnowałem", raz „zrobione" — czyli nie znaczyłoby nic.
     * Skutek czynności mówi ton komunikatu.
     */
    public function open(OverlayInterface $overlay): void
    {
        if ($overlay instanceof Resettable) {
            $overlay->reset();
        }

        $this->current = $overlay;
        $this->events?->publish(AppEvent::OverlayOpened->value);
    }

    /** Ten sam klawisz otwiera okno i je zamyka — jak `F1` i `F2` dla ekranów. */
    public function toggle(OverlayInterface $overlay): void
    {
        if ($this->current === $overlay) {
            $this->close();

            return;
        }

        $this->open($overlay);
    }

    public function close(): void
    {
        $this->current = null;
    }
}
