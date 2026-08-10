<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Odstęp — komponent, który zajmuje miejsce i nie rysuje nic.
 *
 * Wygląda na zbędny, a zastępuje coś gorszego: do kroku 18 odstępy w ekranach
 * ustawień i pomocy były pustymi wierszami `FrameLine::of('')`, czyli **treścią
 * udającą układ**. Wchodziły do rachunku przewijania i do pamięci podręcznej
 * wierszy na równi z prawdziwymi napisami.
 */
final class Spacer implements ComponentInterface
{
    public function draw(Rect $bounds): array
    {
        return [];
    }
}
