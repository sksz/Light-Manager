<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Port\FrameRendererPort;
use LightManager\Application\Ui\Frame;

/**
 * Ujście klatki dla toru taktu pętli: przyjmuje ją i nie rysuje niczego.
 *
 * Nie jest „pustym rendererem” dla samej symetrii — liczy prymitywy, więc
 * tabela ma co postawić w kolumnie rozmiaru: w torze taktu klatka nie ma bajtów,
 * ale ma **objętość**, a ta rośnie razem z tym, co ekran składa.
 */
final class SinkFrameRenderer implements FrameRendererPort
{
    private int $primitives = 0;

    public function render(Frame $frame): void
    {
        $count = 0;

        foreach ($frame->planes as $plane) {
            $count += count($plane->primitives);
        }

        $this->primitives = $count;
    }

    public function primitiveCount(): int
    {
        return $this->primitives;
    }
}
