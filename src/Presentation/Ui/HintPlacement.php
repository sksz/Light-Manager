<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Gdzie w **napisie wiersza** stanęła jedna podpowiedź (krok 55).
 *
 * Współrzędne są tu jeszcze względne — numer wiersza paska i przesunięcie
 * w znakach od jego początku — bo `StatusHints` nie wie, gdzie pasek stoi ani
 * że jego treść jest wyrównana do prawej. Na prostokąt w siatce znakowej
 * przelicza to `StatusBar`, czyli ten, kto tę treść rysuje.
 */
final class HintPlacement
{
    public function __construct(
        public readonly int $line,
        public readonly int $offset,
        public readonly int $length,
        public readonly KeyBinding $binding,
    ) {
    }
}
