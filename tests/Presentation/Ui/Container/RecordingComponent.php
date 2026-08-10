<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Container;

use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\ComponentInterface;

/** Komponent, który nic nie rysuje — zapamiętuje prostokąty, jakie dostał. */
final class RecordingComponent implements ComponentInterface
{
    /** @var list<array{int, int, int, int}> */
    public array $bounds = [];

    public function draw(Rect $bounds): array
    {
        $this->bounds[] = [$bounds->row, $bounds->column, $bounds->rows, $bounds->columns];

        return [];
    }
}
