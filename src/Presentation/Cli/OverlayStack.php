<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

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

    public function current(): ?OverlayInterface
    {
        return $this->current;
    }

    public function isOpen(): bool
    {
        return $this->current !== null;
    }

    public function open(OverlayInterface $overlay): void
    {
        if ($overlay instanceof Resettable) {
            $overlay->reset();
        }

        $this->current = $overlay;
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
