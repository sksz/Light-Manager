<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Presentation\Ui\Resettable;
use LightManager\Presentation\Ui\ScreenInterface;

/**
 * Który ekran jest teraz na wierzchu i dokąd wraca `Esc`.
 *
 * Stos ma dziś dwa piętra: dno i jeden ekran nad nim. Więcej nie było nigdy
 * potrzebne — z ustawień i pomocy wraca się na dno, a nie do poprzedniego okna.
 * Klasa istnieje po to, żeby ta zasada miała jedno miejsce; do kroku 18
 * mieszkała w dwóch `match`-ach po enumie `Screen`.
 *
 * **Dnem jest ekran modułu domyślnego**, a nie przeglądarka plików. Zmiana jest
 * jednowierszowa — do kroku 20 pole nazywało się `$browser` — a znaczy tyle, że
 * `close()` wraca tam, gdzie każe konfiguracja (`startupModule`), i że stos nie
 * zna nazwy żadnego modułu.
 *
 * Skrót modułu domyślnego naciśnięty na jego własnym ekranie **nie robi nic** i
 * wynika to z istniejącego kodu, a nie z przypadku szczególnego: `toggle()`
 * widzi `current === screen`, woła `close()`, a `close()` stawia ten sam ekran.
 */
final class ScreenStack
{
    private ScreenInterface $current;

    public function __construct(
        private readonly ScreenInterface $floor,
    ) {
        $this->current = $floor;
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
        $this->current = $this->floor;
    }
}
