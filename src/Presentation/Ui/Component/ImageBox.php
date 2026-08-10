<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Bitmap;
use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Miejsce na miniaturę wraz z podpisem obok niej.
 *
 * Komponent nie wie, czy obraz się wczyta — to rozstrzyga renderer, bo tylko on
 * ma prawo dotknąć Imagicka. Dlatego podpis idzie razem ze ścieżką w jednym
 * prymitywie: przy powodzeniu staje obok miniatury, przy niepowodzeniu — w
 * środku pustej ramki, żeby prostokąt nie wyglądał jak błąd rysowania.
 */
final class ImageBox implements ComponentInterface
{
    /** Miniatura dostaje najwyżej tę część szerokości pasa. */
    private const WIDTH_DIVISOR = 3;

    public function __construct(
        private readonly ?string $path,
        private readonly string $caption,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        return [
            new Bitmap(
                new Rect(
                    $bounds->row,
                    $bounds->column,
                    $bounds->rows,
                    max(1, intdiv($bounds->columns, self::WIDTH_DIVISOR)),
                ),
                $this->path,
                $this->caption,
            ),
        ];
    }
}
