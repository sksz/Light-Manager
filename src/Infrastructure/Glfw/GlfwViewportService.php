<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

use LightManager\Application\Port\ViewportPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Rozmiar obszaru rysowania w komórkach — okienna implementacja
 * `ViewportPort`: framebuffer podzielony przez komórkę z metryk fontu
 * (od kroku 35; do tego czasu komórka była stałą zastępczą).
 *
 * Rozmiar czyta się **przy każdym pytaniu** — składanie klatki i renderer
 * pytają co klatkę (wzorzec kroku 33), a GLFW oddaje framebuffer tanim
 * wywołaniem w procesie, więc znacznik i ponowny pomiar znane z toru
 * terminalowego są tu niepotrzebne. Przeciągnięcie rogu okna jest przez to
 * widoczne od następnej klatki bez ani jednej linii kodu obsługi zdarzeń —
 * usługa celowo nie ma ani jednego pola, żeby nie miała czego zapamiętać
 * (pilnuje tego test).
 */
final class GlfwViewportService extends AbstractSingleton implements ViewportPort
{
    public function rows(): int
    {
        return self::cells(
            GlfwWindowService::getInstance()->framebufferSize()['height'],
            VgContextService::getInstance()->cellHeightPixels(),
        );
    }

    public function columns(): int
    {
        return self::cells(
            GlfwWindowService::getInstance()->framebufferSize()['width'],
            VgContextService::getInstance()->cellWidthPixels(),
        );
    }

    /**
     * Piksele → pełne komórki, nigdy mniej niż jedna: okno ściśnięte poniżej
     * komórki dalej rysuje, co się zmieści (reguła kroku 33 — planszy
     * zastępczej nie ma). Publiczne i statyczne, bo to jedyna arytmetyka tej
     * usługi i jedyne, co daje się sprawdzić bez otwartego okna.
     */
    public static function cells(int $pixels, int $cellPixels): int
    {
        return max(1, intdiv($pixels, max(1, $cellPixels)));
    }
}
