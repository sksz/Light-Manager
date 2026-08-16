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
 *
 * Od kroku 55 pozycja niesie obok napisu **wiązanie, z którego powstała** —
 * i to jest cała treść zdania „pozycja stopki musi poznać swoją kolumnę”.
 * Kliknięcie w podpowiedź zamienia się z powrotem w `KeyPress` i wraca do
 * `InputHandler::handle()`, czyli wykonuje się **tą samą drogą co klawisz**,
 * a nie drugą, równoległą. Wiązanie bywa puste (`null`) wyłącznie tam, gdzie
 * pozycja powstaje z gotowego napisu, a nie z klawisza — czyli w obciążeniu
 * pomiarowym `ScenarioFactory`, gdzie klikać nie ma kto.
 */
final class Hint
{
    public function __construct(
        public readonly string $text,
        /** Czy pozycja ustępuje dopiero wtedy, gdy nie mieści się sama jedna. */
        public readonly bool $pinned = false,
        public readonly ?KeyBinding $binding = null,
    ) {
    }
}
