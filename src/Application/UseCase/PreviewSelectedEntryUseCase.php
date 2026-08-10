<?php

declare(strict_types=1);

namespace LightManager\Application\UseCase;

use LightManager\Application\Dto\ImageMetadata;
use LightManager\Application\Port\ImagePreviewPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\Aggregate\Directory;
use LightManager\Domain\ValueObject\Entry;
use LightManager\Domain\ValueObject\Preview;

/**
 * Ustala, co pokazać w pasie podglądu pod listą.
 *
 * Rozpoznawanie idzie dwustopniowo: najpierw rozszerzenie (czysta decyzja, bez
 * dotykania dysku), potem odczyt nagłówka przez port. Dzięki temu archiwa i
 * filmy nie trafiają do Imagicka, a plik z rozszerzeniem obrazu, którego nie da
 * się odczytać, kończy czytelnym powodem zamiast wyjątkiem.
 *
 * Wynik jest zapamiętywany dla ostatniego wpisu, bo pętla główna składa klatkę
 * 20 razy na sekundę — bez tego każdy takt oznaczałby ponowne otwarcie pliku.
 */
final class PreviewSelectedEntryUseCase
{
    /**
     * Filtr wstępny. Lista jest celowo krótka: obejmuje formaty, których
     * podgląd ma sens w menadżerze plików, a nie wszystko, co Imagick umie
     * otworzyć przez zewnętrzne delegaty.
     */
    private const IMAGE_EXTENSIONS = [
        'avif', 'bmp', 'gif', 'heic', 'heif', 'ico', 'jpeg', 'jpg',
        'png', 'ppm', 'svg', 'tga', 'tif', 'tiff', 'webp', 'xpm',
    ];

    private const MAXIMUM_FILE_BYTES = 32 * 1024 * 1024;

    /** Limit rozdzielczości wejściowej — 50 Mpx to ok. 200 MB w pamięci przy RGBA. */
    private const MAXIMUM_INPUT_PIXELS = 50000000;

    private const MEGAPIXEL = 1000000;

    private ?string $cacheKey = null;

    private ?Preview $cached = null;

    public function __construct(
        private readonly ImagePreviewPort $images,
        private readonly TranslatorPort $translator,
    ) {
    }

    /**
     * `null` znaczy „nic do pokazania” — pas zostaje pusty, ale nie znika,
     * bo jego wiersze odjęto liście już przy liczeniu pojemności.
     *
     * O wysokość pasa nie pytamy: podgląd niesie ścieżkę i podpis, a ile
     * wierszy dostał, wie `FrameLayout` (krok 17, usunięcie `Preview::rows`).
     */
    public function execute(Directory $directory): ?Preview
    {
        $entry = $directory->selectedEntry();

        if ($entry === null || $entry->isDirectory() || !$this->looksLikeImage($entry->name)) {
            return null;
        }

        $path = $directory->path()->child($entry->name)->value;

        // Rozmiar w kluczu wystarcza za znacznik zmiany: podmieniony plik o
        // dokładnie tej samej długości to przypadek, którym nie warto płacić
        // dodatkowym `stat` na każdą klatkę.
        $key = $path . '|' . $entry->sizeInBytes;

        if ($this->cacheKey === $key && $this->cached !== null) {
            return $this->cached;
        }

        $this->cacheKey = $key;

        return $this->cached = $this->inspect($path, $entry);
    }

    private function inspect(string $path, Entry $entry): Preview
    {
        if ($entry->sizeInBytes > self::MAXIMUM_FILE_BYTES) {
            return Preview::unavailable(
                $this->translator->translate('preview.tooLarge', [
                    'limit' => intdiv(self::MAXIMUM_FILE_BYTES, 1024 * 1024),
                ]),
            );
        }

        $metadata = $this->images->inspect($path);

        if ($metadata === null) {
            return Preview::unavailable($this->translator->translate('preview.unreadable'));
        }

        if ($metadata->pixels() > self::MAXIMUM_INPUT_PIXELS) {
            return Preview::unavailable(
                $this->translator->translate('preview.tooManyPixels', [
                    'dimensions' => $this->dimensions($metadata),
                    'limit' => intdiv(self::MAXIMUM_INPUT_PIXELS, self::MEGAPIXEL),
                ]),
            );
        }

        return Preview::ofImage(
            $path,
            sprintf('%s  %s', $this->dimensions($metadata), $metadata->format),
        );
    }

    private function dimensions(ImageMetadata $metadata): string
    {
        return sprintf('%d×%d', $metadata->widthPixels, $metadata->heightPixels);
    }

    private function looksLikeImage(string $name): bool
    {
        $dot = strrpos($name, '.');

        if ($dot === false || $dot === 0) {
            return false;
        }

        return in_array(strtolower(substr($name, $dot + 1)), self::IMAGE_EXTENSIONS, true);
    }
}
