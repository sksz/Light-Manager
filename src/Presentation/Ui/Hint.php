<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Jedna pozycja paska stanu: gotowy napis wraz z tym, czy wolno go pominąć.
 *
 * Napis jest **złożony i przetłumaczony** — klawisze z `KeyBinding::display()`,
 * opis z katalogu. Dalej nikt już nie pyta, z czego powstał, bo ustępowanie
 * i pakowanie w wiersze liczą wyłącznie znaki.
 *
 * Przypięta jest dziś jedna pozycja: `F1`. Ustępuje ostatnia, bo bez niej znika
 * jedyne wskazanie, gdzie leży pełny spis klawiszy (krok 40, rozstrzygnięcie
 * nr 8).
 */
final class Hint
{
    public function __construct(
        public readonly string $text,
        /** Czy pozycja ustępuje dopiero wtedy, gdy nie mieści się sama jedna. */
        public readonly bool $pinned = false,
    ) {
    }
}
