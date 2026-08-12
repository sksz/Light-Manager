<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

use GL\Texture\Texture2D;
use GL\VectorGraphics\VGImage;
use Throwable;

/**
 * Tekstury podglądów obrazów z limitem — okienny odpowiednik pamięci bitmap
 * z kroku 17 i `ThumbnailService` z kroku 12 (krok 35). Druga klatka z tą samą
 * miniaturą nie ma prawa dekodować jej ponownie: piksele siedzą już na GPU.
 *
 * Klucz niesie ścieżkę wraz z czasem modyfikacji i rozmiarem pliku — plik
 * podmieniony pod tą samą nazwą dostaje świeżą teksturę, dokładnie jak
 * w `ThumbnailService`. Wpis `null` też jest wpisem: plik nieczytelny nie
 * jest dekodowany w kółko trzydzieści razy na sekundę.
 *
 * Limity rozmiaru stoją tu, a nie w D24: tor okienkowy dekoduje natywnie
 * (stb_image, D54), więc ochrona potoku Imagicka go nie obejmuje. Wartości
 * te same co w D24 — nie z sentymentu, lecz dlatego, że opisują ten sam
 * budżet: plik, którego dekodowanie zamroziłoby pętlę.
 */
final class VgTextureCache
{
    /** Wpisów w pamięci; tekstura potrafi ważyć megabajty, więc mniej niż bitmap wierszy. */
    private const ENTRY_LIMIT = 32;

    /** Te same progi co D24 — opisują budżet dekodowania, nie potok Imagicka. */
    private const MAX_FILE_BYTES = 32 * 1024 * 1024;

    private const MAX_PIXELS = 50_000_000;

    /** @var array<string, array{image: VGImage, width: int, height: int}|null> */
    private array $entries = [];

    /** @var callable(string): (array{image: VGImage, width: int, height: int}|null) */
    private $loader;

    /** @param (callable(string): (array{image: VGImage, width: int, height: int}|null))|null $loader dubler dla testów */
    public function __construct(?callable $loader = null)
    {
        $this->loader = $loader ?? $this->defaultLoader(...);
    }

    /** @return array{image: VGImage, width: int, height: int}|null `null` — pliku nie da się pokazać */
    public function textureFor(string $path): ?array
    {
        $key = $path . '|' . (string) @filemtime($path) . '|' . (string) @filesize($path);

        if (array_key_exists($key, $this->entries)) {
            // Odświeżenie pozycji w porządku LRU — wyrzucamy najdawniej użyty,
            // nie najdawniej wstawiony.
            $entry = $this->entries[$key];
            unset($this->entries[$key]);

            return $this->entries[$key] = $entry;
        }

        if (count($this->entries) >= self::ENTRY_LIMIT) {
            // Najstarszy wpis wypada w całości; teksturę zwalnia destruktor
            // obiektu rozszerzenia, gdy zniknie ostatnie odwołanie.
            unset($this->entries[array_key_first($this->entries)]);
        }

        return $this->entries[$key] = ($this->loader)($path);
    }

    /** @return array{image: VGImage, width: int, height: int}|null */
    private function defaultLoader(string $path): ?array
    {
        $bytes = @filesize($path);

        if ($bytes === false || $bytes > self::MAX_FILE_BYTES) {
            return null;
        }

        try {
            $texture = Texture2D::fromDisk($path, Texture2D::CHANNEL_RGBA);
        } catch (Throwable) {
            // Format spoza zakresu stb_image albo plik uszkodzony — dla klatki
            // to ta sama odpowiedź, co plik nieczytelny: ramka z podpisem.
            return null;
        }

        $width = $texture->width();
        $height = $texture->height();

        if ($width < 1 || $height < 1 || $width * $height > self::MAX_PIXELS) {
            return null;
        }

        return [
            'image' => VgContextService::getInstance()->context()->imageFromTexture(
                $texture,
                VGImage::REPEAT_NONE,
                VGImage::FILTER_LINEAR,
            ),
            'width' => $width,
            'height' => $height,
        ];
    }
}
