<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

use LightManager\Application\Dto\InputEvent;

/**
 * Jedno zdarzenie wyjęte z bufora bajtów wraz z jego długością.
 *
 * Od kroku 55 niesie `InputEvent`, a nie `KeyPress`: ta sama sekwencja CSI, na
 * której stoją strzałki, niesie w trybie SGR także kliknięcia — więc rozbiór
 * jest jeden, a wynik ma dwie postacie. Nazwa klasy zostaje, bo opisuje
 * czynność (co rozebrano), a nie typ wyniku.
 */
final class ParsedKey
{
    public function __construct(
        public readonly InputEvent $event,
        public readonly int $consumedBytes,
    ) {
    }
}
