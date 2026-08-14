<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use GL\Buffer\UByteBuffer;
use Imagick;
use LightManager\Application\Ui\Frame;
use LightManager\Infrastructure\Glfw\GlfwWindowService;

/**
 * Zrzut toru okienkowego: **bufor karty** odczytany po tym, jak klatka trafiła
 * na ekran (krok 38).
 *
 * Klatki nie rysujemy drugi raz — okno właśnie ją pokazało, a `glReadPixels`
 * czyta to, co naprawdę zobaczył użytkownik. Dwie rzeczy są obowiązkowe:
 * `glFinish()` przed odczytem, bo sterownik mógł jeszcze nie skończyć rysować,
 * i odwrócenie w pionie — początek układu OpenGL leży w lewym dolnym rogu,
 * a obrazu w lewym górnym.
 */
final class WindowFrameGrabber implements FrameImageGrabber
{
    public function imageOf(Frame $frame): Imagick
    {
        glFinish();

        ['width' => $width, 'height' => $height] = GlfwWindowService::getInstance()->framebufferSize();
        $pixels = new UByteBuffer();
        $pixels->reserve($width * $height * 4);
        glReadPixels(0, 0, $width, $height, GL_RGBA, GL_UNSIGNED_BYTE, $pixels);

        $image = new Imagick();
        $image->readImageBlob(sprintf(
            "P7\nWIDTH %d\nHEIGHT %d\nDEPTH 4\nMAXVAL 255\nTUPLTYPE RGB_ALPHA\nENDHDR\n",
            $width,
            $height,
        ) . $pixels->dump());
        $image->flipImage();

        return $image;
    }
}
