<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Port\FrameRendererPort;
use LightManager\Application\Ui\Frame;

/**
 * Renderer opakowany zamówieniem zrzutu (krok 38).
 *
 * Dekorator, a nie zmiana w `FrameComposer`, i to jest tu rozstrzygnięcie:
 * składanie klatki zostaje nietknięte, a jedyny koszt w ścieżce klatki to
 * **sprawdzenie jednego pola** po narysowaniu. D28 („zero wywołań pomiarowych
 * w kodzie produkcyjnym”) zostaje w mocy — to nie jest pomiar, tylko zapis na
 * żądanie użytkownika, i dzieje się dopiero wtedy, gdy on o niego poprosi.
 *
 * Zrzut robimy **po** `render()`, bo tor okienkowy czyta bufor karty: przed
 * narysowaniem nie ma tam jeszcze czego czytać.
 */
final class DumpingFrameRenderer implements FrameRendererPort
{
    public function __construct(
        private readonly FrameRendererPort $renderer,
        private readonly FrameDumpService $dumps,
    ) {
    }

    public function render(Frame $frame): void
    {
        $this->renderer->render($frame);

        if ($this->dumps->isPending()) {
            $this->dumps->captureIfRequested($frame);
        }
    }
}
