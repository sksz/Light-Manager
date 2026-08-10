<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

/**
 * Rozmiar obszaru, na którym rysowana jest klatka, w komórkach znakowych.
 *
 * Dzięki temu portowi warstwa aplikacji może policzyć okno przewijania i
 * dopasować szerokość wierszy, nie wiedząc nic o terminalu.
 */
interface ViewportPort
{
    public function rows(): int;

    public function columns(): int;
}
