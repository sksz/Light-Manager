<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Port\FrameRendererPort;
use LightManager\Application\Ui\Frame;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;

/**
 * Renderer, który niczego nie rysuje — zapamiętuje klatki, żeby test mógł
 * sprawdzić, co aplikacja chciała pokazać.
 */
final class RecordingRenderer implements FrameRendererPort
{
    /** @var list<Frame> */
    public array $frames = [];

    public function render(Frame $frame): void
    {
        $this->frames[] = $frame;
    }

    public function last(): ?Frame
    {
        return $this->frames === [] ? null : $this->frames[count($this->frames) - 1];
    }

    /**
     * Wszystkie prymitywy ostatniej klatki, w kolejności rysowania.
     *
     * @return list<Primitive>
     */
    public function primitives(): array
    {
        $primitives = [];
        $last = $this->last();

        foreach ($last === null ? [] : $last->planes as $plane) {
            foreach ($plane->primitives as $primitive) {
                $primitives[] = $primitive;
            }
        }

        return $primitives;
    }

    /**
     * Napisy ostatniej klatki — najwygodniejszy sposób, by sprawdzić treść bez
     * zaglądania w piksele.
     *
     * @return list<string>
     */
    public function texts(): array
    {
        $texts = [];

        foreach ($this->primitives() as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }

    public function showsText(string $needle): bool
    {
        foreach ($this->texts() as $text) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }
}
