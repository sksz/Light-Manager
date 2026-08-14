<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use Imagick;
use LightManager\Application\Ui\Frame;
use LightManager\Infrastructure\Rendering\AnsiPalette;
use LightManager\Infrastructure\Rendering\TextFrameRenderer;
use LightManager\Infrastructure\Rendering\ThemeService;
use LightManager\Infrastructure\Terminal\TerminalSizeService;

/**
 * Zrzut toru tekstowego: bajty ANSI zrasteryzowane fontem stałej szerokości
 * (krok 38).
 *
 * Rasteryzujemy **bajty**, a nie bufor komórek, bo dopiero w bajtach kolory
 * przeszły przez zaokrąglenie do palety terminala. Obraz pokazuje więc barwy,
 * które użytkownik naprawdę zobaczy, a nie te, o które prosił motyw.
 */
final class TextFrameGrabber implements FrameImageGrabber
{
    public function imageOf(Frame $frame): Imagick
    {
        $size = TerminalSizeService::getInstance()->size();
        $theme = ThemeService::getInstance()->active();
        $renderer = new TextFrameRenderer(AnsiPalette::fromEnvironment());

        return (new AnsiRasterizer())->rasterize(
            $renderer->encode($renderer->composeBuffer($frame, $theme, $size->rows, $size->columns)),
            $theme->background,
        );
    }
}
