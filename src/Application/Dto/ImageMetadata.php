<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * To, co da się powiedzieć o obrazie bez jego dekodowania — sam nagłówek pliku.
 *
 * Wymiary są potrzebne przed decyzją o podglądzie: obraz o zbyt dużej
 * rozdzielczości ma zostać odrzucony, zanim ktokolwiek spróbuje go wczytać.
 */
final class ImageMetadata
{
    public function __construct(
        public readonly int $widthPixels,
        public readonly int $heightPixels,
        public readonly string $format,
    ) {
    }

    public function pixels(): int
    {
        return $this->widthPixels * $this->heightPixels;
    }
}
