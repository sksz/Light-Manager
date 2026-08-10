<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Imagick;

use Imagick;
use ImagickException;
use LightManager\Application\Dto\ImageMetadata;
use LightManager\Application\Port\ImagePreviewPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Czyta sam nagłówek pliku graficznego (`pingImage`), bez dekodowania pikseli.
 *
 * Plik jest otwierany jako uchwyt, a nie podawany Imagickowi po nazwie: nazwa
 * bywa dla ImageMagicka poleceniem (prefiks kodera, selektor klatki w
 * nawiasach kwadratowych), a nazwy plików w katalogu użytkownika nie są pod
 * naszą kontrolą.
 */
final class ImagePreviewService extends AbstractSingleton implements ImagePreviewPort
{
    public function inspect(string $path): ?ImageMetadata
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            $image = new Imagick();
            $image->pingImageFile($handle);

            $width = $image->getImageWidth();
            $height = $image->getImageHeight();
            $format = $image->getImageFormat();

            $image->clear();
        } catch (ImagickException) {
            return null;
        } finally {
            fclose($handle);
        }

        // Nagłówek odczytany, ale bez sensownych wymiarów — dla nas to tyle, co
        // brak obrazu; renderer nie miałby czego skalować.
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return new ImageMetadata($width, $height, $format);
    }
}
