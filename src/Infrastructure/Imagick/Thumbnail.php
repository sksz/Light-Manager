<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Imagick;

use Imagick;

/**
 * Miniatura wraz z listą kolorów, które w niej zostały.
 *
 * Kolory jadą razem z obrazem, bo paleta klatki z podglądem składa się z dwóch
 * części: wpisów motywu, znanych z góry, i barw zdjęcia, których nikt z góry nie
 * zna (`ThemePalette::forThemeWithImage()`). Policzenie ich w miejscu, w którym
 * miniatura powstaje, sprawia, że histogram liczy się raz na plik, a nie raz na
 * klatkę — a klatka powstaje trzydzieści razy na sekundę.
 *
 * Obraz należy do `ThumbnailService` i żyje do następnego wywołania z innym
 * kluczem: wolno go komponować na płótno, ale nie modyfikować.
 */
final class Thumbnail
{
    /**
     * @param list<string> $colors barwy miniatury po kwantyzacji, w zapisie `#rrggbb`
     */
    public function __construct(
        public readonly Imagick $image,
        public readonly array $colors,
    ) {
    }
}
