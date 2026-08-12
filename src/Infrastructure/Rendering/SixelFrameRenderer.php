<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Rendering;

use LightManager\Application\Port\FrameRendererPort;
use LightManager\Application\Ui\Frame;
use LightManager\Infrastructure\Imagick\SixelFrameEncoder;
use LightManager\Infrastructure\Terminal\TerminalService;
use LightManager\Infrastructure\Terminal\TerminalSize;
use LightManager\Infrastructure\Terminal\TerminalSizeService;

/**
 * Wypycha klatkę na terminal jako obraz Sixel.
 *
 * Płótno wypełnia okno poza ostatnim wierszem, więc kolejna klatka zamalowuje
 * poprzednią w całości — wystarczy przed każdym rysowaniem odesłać kursor do
 * lewego górnego rogu. Czyszczenie ekranu byłoby tu zbędne i dawałoby
 * migotanie. Ostatni wiersz okna zostaje pusty i taki pozostaje: nikt tam nic
 * nie pisze, więc nie ma czego sprzątać (powód rezerwy:
 * `TerminalSize::heightPixelsWithoutBottomRow()`).
 *
 * Podział na strefy przychodzi razem z treścią, z tego samego rachunku, którym
 * warstwa aplikacji policzyła pojemność listy. Opcje renderowania składane są
 * tutaj i tylko na jedną klatkę, bo użytkownik ma prawo zmienić je w trakcie.
 */
final class SixelFrameRenderer implements FrameRendererPort
{
    private const CURSOR_HOME = "\e[H";

    private const CLEAR_SCREEN = "\e[2J";

    /** Rozmiar poprzedniej klatki — po nim renderer poznaje zmianę okna. */
    private ?TerminalSize $lastSize = null;

    public function __construct(
        private readonly SixelFrameEncoder $encoder,
    ) {
    }

    public function render(Frame $frame): void
    {
        $size = TerminalSizeService::getInstance()->size();

        // Reguła „zamalować poprzednią klatkę” z komentarza wyżej stoi na
        // płótnie o stałym rozmiarze i zmiana okna łamie ją w obie strony:
        // po zmniejszeniu terminal łamie i przewija stare wiersze, po
        // powiększeniu nowa klatka nie sięga tam, gdzie leżą resztki starej.
        // Jednorazowe czyszczenie to jawny wyjątek (krok 33): mignąć może
        // jedna klatka po zmianie, nie każda.
        $prefix = $this->lastSize !== null && !$this->lastSize->equals($size)
            ? self::CLEAR_SCREEN . self::CURSOR_HOME
            : self::CURSOR_HOME;

        $this->lastSize = $size;

        TerminalService::getInstance()->write(
            $prefix . $this->encoder->encode(
                $frame,
                RenderingOptions::current(),
                $size->widthPixels,
                $size->heightPixelsWithoutBottomRow(),
                $size->rows,
                $size->columns,
            ),
        );
    }
}
