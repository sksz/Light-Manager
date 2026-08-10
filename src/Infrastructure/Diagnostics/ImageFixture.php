<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use Imagick;
use ImagickDraw;
use ImagickPixel;

/**
 * Obraz, na którym mierzony jest scenariusz z miniaturą.
 *
 * Powstaje w katalogu tymczasowym przy każdym uruchomieniu i znika po nim —
 * plik binarny w repozytorium byłby zbędny, skoro treść da się odtworzyć kodem.
 * Treść jest **deterministyczna** (gradient plus ustalone koła), więc dwa
 * przebiegi dostają ten sam materiał do dekodowania; `plasma:` czy `random:`
 * dawałyby za każdym razem inny rozkład barw, a przez to inną kwantyzację.
 *
 * Rozdzielczość 1600×1200 jest celowo większa od pasa podglądu — dekoder ma
 * naprawdę skalować w dół, a nie przepisywać piksel w piksel.
 */
final class ImageFixture
{
    public const WIDTH_PIXELS = 1600;

    public const HEIGHT_PIXELS = 1200;

    private function __construct(
        public readonly string $path,
    ) {
    }

    /**
     * @throws DiagnosticsException gdy katalog tymczasowy jest niezapisywalny
     */
    public static function create(): self
    {
        $path = tempnam(sys_get_temp_dir(), 'light-manager-bench-');

        if ($path === false) {
            throw DiagnosticsException::forFailedWrite(sys_get_temp_dir());
        }

        $file = $path . '.jpg';
        @unlink($path);

        $image = new Imagick();
        $image->newPseudoImage(
            self::WIDTH_PIXELS,
            self::HEIGHT_PIXELS,
            'gradient:#1b3b6f-#d9a441',
        );

        $image->drawImage(self::circles());
        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(85);

        $written = $image->writeImage($file);
        $image->clear();

        if (!$written) {
            throw DiagnosticsException::forFailedWrite($file);
        }

        return new self($file);
    }

    public function remove(): void
    {
        @unlink($this->path);
    }

    /** Koła w ustalonych miejscach — dają dekoderowi krawędzie, nie sam gradient. */
    private static function circles(): ImagickDraw
    {
        $draw = new ImagickDraw();

        for ($index = 0; $index < 12; ++$index) {
            $draw->setFillColor(new ImagickPixel($index % 2 === 0 ? '#f2f4f7' : '#e0645c'));

            $centreX = 120 + $index * 120;
            $centreY = 200 + ($index % 5) * 180;

            $draw->circle($centreX, $centreY, $centreX + 45 + $index * 3, $centreY);
        }

        return $draw;
    }
}
