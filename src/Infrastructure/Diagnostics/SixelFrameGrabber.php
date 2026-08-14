<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use Imagick;
use LightManager\Application\Ui\Frame;
use LightManager\Infrastructure\Imagick\SixelFrameEncoder;
use LightManager\Infrastructure\Rendering\RenderingOptions;
use LightManager\Infrastructure\Terminal\TerminalSizeService;

/**
 * Zrzut toru sixelowego: klatka **narysowana ponownie** tym samym potokiem
 * i zatrzymana po kwantyzacji (krok 38).
 *
 * Ponowne rysowanie jest tu uczciwe, bo potok jest deterministyczny — ta sama
 * klatka przy tych samych ustawieniach daje bajt w bajt ten sam obraz. Obraz
 * bierzemy **po** kwantyzacji, bo to ona zjadała obwódki w kroku 13, a zrzut
 * ma pokazywać to, co widzi użytkownik, a nie to, co narysował enkoder.
 */
final class SixelFrameGrabber implements FrameImageGrabber
{
    public function imageOf(Frame $frame): Imagick
    {
        $encoder = new SixelFrameEncoder();
        $size = TerminalSizeService::getInstance()->size();
        $canvas = $encoder->drawCanvas(
            $frame,
            RenderingOptions::current(),
            $size->widthPixels,
            $size->heightPixelsWithoutBottomRow(),
            $size->rows,
            $size->columns,
        );

        $encoder->quantizeCanvas($canvas, $encoder->canvasCarriesBitmap());

        return $canvas;
    }
}
