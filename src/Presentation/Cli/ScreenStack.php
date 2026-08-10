<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;

/**
 * Który ekran jest teraz na wierzchu i dokąd wraca `Esc`.
 *
 * Stos ma dziś dwa piętra: przeglądarkę plików, która jest dnem, i jeden ekran
 * nad nią. Więcej nie było nigdy potrzebne — z ustawień i pomocy wraca się do
 * listy, a nie do poprzedniego okna. Klasa istnieje po to, żeby ta zasada miała
 * jedno miejsce; do kroku 18 mieszkała w dwóch `match`-ach po enumie `Screen`.
 */
final class ScreenStack
{
    private ScreenInterface $current;

    public function __construct(
        private readonly ScreenInterface $browser,
    ) {
        $this->current = $browser;
    }

    public function current(): ScreenInterface
    {
        return $this->current;
    }

    /**
     * Otwiera ekran, a jeśli już jest otwarty — zamyka go. Dzięki temu ten sam
     * klawisz wchodzi do ustawień i z nich wychodzi, bez osobnego wiązania.
     */
    public function toggle(ScreenInterface $screen): void
    {
        if ($this->current === $screen) {
            $this->close();

            return;
        }

        $this->open($screen);
    }

    public function open(ScreenInterface $screen): void
    {
        if ($screen instanceof Resettable) {
            $screen->reset();
        }

        $this->current = $screen;
    }

    public function close(): void
    {
        $this->current = $this->browser;
    }
}
