<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Imagick;

use Imagick;
use ImagickException;
use ImagickPixel;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Skaluje plik graficzny do rozmiaru pasa podglądu i sprowadza go do zadanej
 * liczby kolorów.
 *
 * Wynik jest zapamiętywany dla ostatniego pliku, bo pętla główna składa klatkę
 * 30 razy na sekundę, a dekodowanie zdjęcia trwa dziesiątki milisekund — bez
 * pamięci podręcznej samo stanie na jednym wpisie zjadałoby cały budżet taktu.
 * **Kwantyzacja siedzi tutaj z tego samego powodu**: barwy zdjęcia zmieniają się
 * razem z plikiem, a nie razem z klatką, więc płaci się za nie raz na najechany
 * wpis.
 *
 * Zwrócony obraz należy do usługi i żyje do następnego wywołania z innym
 * kluczem — wolno go komponować na płótno, ale nie modyfikować.
 */
final class ThumbnailService extends AbstractSingleton
{
    private ?string $cacheKey = null;

    private ?Thumbnail $cached = null;

    /**
     * `null`, gdy pliku nie da się odczytać albo zdekodować.
     *
     * `$colors` to sufit palety miniatury — tyle wpisów zostaje jej w palecie
     * klatki po odliczeniu kolorów motywu (`ThemePalette::roomForImage()`).
     */
    public function thumbnail(
        string $path,
        int $maxWidthPixels,
        int $maxHeightPixels,
        string $backgroundColor,
        int $colors,
    ): ?Thumbnail {
        if ($maxWidthPixels < 1 || $maxHeightPixels < 1) {
            return null;
        }

        $key = implode('|', [
            $path,
            (string) @filemtime($path),
            (string) @filesize($path),
            $maxWidthPixels,
            $maxHeightPixels,
            $backgroundColor,
            $colors,
        ]);

        if ($this->cacheKey === $key) {
            return $this->cached;
        }

        $this->cached?->image->clear();

        $this->cacheKey = $key;

        return $this->cached = $this->scale($path, $maxWidthPixels, $maxHeightPixels, $backgroundColor, $colors);
    }

    private function scale(
        string $path,
        int $maxWidthPixels,
        int $maxHeightPixels,
        string $backgroundColor,
        int $colors,
    ): ?Thumbnail {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            $source = new Imagick();

            // Dekoder JPEG-a potrafi rozpakować obraz od razu w zmniejszonej
            // skali (DCT), zamiast rozwijać pełną rozdzielczość tylko po to,
            // żeby ją zaraz zmniejszyć. Dla zdjęcia z aparatu to różnica
            // rzędu wielkości. Formaty bez takiej możliwości opcję ignorują.
            $source->setOption('jpeg:size', $maxWidthPixels . 'x' . $maxHeightPixels);

            $source->readImageFile($handle);

            // Animacje i ikony wielorozmiarowe niosą wiele klatek — bierzemy
            // pierwszą, bo pas podglądu to jeden nieruchomy obrazek.
            $source->setIteratorIndex(0);
            $frame = $source->getImage();
            $source->clear();

            // Przezroczystość bez tego wyszłaby czarna albo szachownicą —
            // spłaszczamy na tło klatki, żeby miniatura wtopiła się w pas.
            $frame->setImageBackgroundColor(new ImagickPixel($backgroundColor));
            $flattened = $frame->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $frame->clear();

            $flattened->thumbnailImage($maxWidthPixels, $maxHeightPixels, true);

            // Zdjęcie sprowadzamy do palety **tutaj, zanim trafi na płótno**.
            // Kwantyzacja gotowej klatki liczyłaby paletę z zawartości zdjęcia
            // i przyciągnęła do niej kolory interfejsu — akcent Grafitu
            // `#d9a441` lądował wtedy na `#b15f0d`, a tło `#16181c` na
            // `#020203`, czyli najechanie na plik graficzny przemalowywało całą
            // aplikację. Policzone tu barwy dopisują się do palety motywu
            // zamiast ją wypierać (`ThemePalette::forThemeWithImage()`).
            $flattened->quantizeImage(max(1, $colors), Imagick::COLORSPACE_RGB, 0, false, false);

            return new Thumbnail($flattened, self::colorsOf($flattened));
        } catch (ImagickException) {
            return null;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Barwy, które w miniaturze zostały po kwantyzacji.
     *
     * @return list<string>
     */
    private static function colorsOf(Imagick $image): array
    {
        $colors = [];

        foreach ($image->getImageHistogram() as $pixel) {
            /** @var array{r: int, g: int, b: int} $channels */
            $channels = $pixel->getColor();

            $colors[] = sprintf('#%02x%02x%02x', $channels['r'], $channels['g'], $channels['b']);
        }

        return array_values(array_unique($colors));
    }
}
