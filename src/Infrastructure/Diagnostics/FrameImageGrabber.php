<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use Imagick;
use LightManager\Application\Ui\Frame;

/**
 * Sposób, w jaki dany tor oddaje **obraz** swojej klatki na potrzeby zrzutu
 * z żywej aplikacji (krok 38).
 *
 * Torów jest trzy i każdy oddaje go inaczej — płótnem Imagicka, buforem karty
 * albo rasteryzacją bajtów ANSI. Rozstrzygnięcie użytkownika (D64) brzmiało:
 * **wiernie każdemu torowi**, więc wspólnego skrótu tu nie ma i mieć nie
 * powinno; zrzut z jednego toru rysowany kodem drugiego byłby dowodem na cudzą
 * klatkę.
 */
interface FrameImageGrabber
{
    /** Zwolnienie (`clear()`) należy do wołającego. */
    public function imageOf(Frame $frame): Imagick;
}
