<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Diagnostics;

use LightManager\Infrastructure\Diagnostics\AnsiRasterizer;
use PHPUnit\Framework\TestCase;

/**
 * Zrzut toru tekstowego ma pokazywać **to, co widzi użytkownik** — więc test
 * pilnuje dwóch rzeczy, na których to stoi: że sekwencje sterujące nie wchodzą
 * do obrazu jako tekst i że kolor z palety 256 wraca do właściwej barwy.
 */
final class AnsiRasterizerTest extends TestCase
{
    public function testControlSequencesDoNotLandInTheImage(): void
    {
        // Ustawienie kursora i wyczyszczenie ekranu poprzedzają każdą klatkę
        // trybu tekstowego. Zanim rasteryzator nauczył się je zjadać, obraz
        // zaczynał się napisem „[H [2J”.
        $image = (new AnsiRasterizer())->rasterize("\e[H\e[2J\e[38;5;196mA\e[0m");

        try {
            self::assertSame(8, $image->getImageWidth(), 'jedna komórka, a nie dziewięć');
            self::assertSame(16, $image->getImageHeight());
        } finally {
            $image->clear();
        }
    }

    /** Wiersze rozdziela `\r\n`, więc obraz rośnie w dół, a nie w bok. */
    public function testEveryLineBecomesARowOfCells(): void
    {
        $image = (new AnsiRasterizer())->rasterize("ab\r\ncd\r\nef");

        try {
            self::assertSame(16, $image->getImageWidth());
            self::assertSame(48, $image->getImageHeight());
        } finally {
            $image->clear();
        }
    }

    /**
     * Kolor tła z palety 256 ma wrócić do barwy, którą wypisał `AnsiPalette` —
     * to jest cały powód, dla którego rasteryzujemy **bajty**, a nie bufor
     * komórek: obraz pokazuje barwę po zaokrągleniu do palety terminala.
     */
    public function testBackgroundFromThe256PaletteComesBackAsItsColour(): void
    {
        // 196 to czysta czerwień w sześcianie kolorów xterma (#ff0000).
        $image = (new AnsiRasterizer())->rasterize("\e[48;5;196m \e[0m");

        try {
            self::assertSame('srgb(255,0,0)', $image->getImagePixelColor(4, 8)->getColorAsString());
        } finally {
            $image->clear();
        }
    }
}
