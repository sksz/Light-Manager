<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\UseCase;

use LightManager\Application\Dto\ImageMetadata;
use LightManager\Application\Port\ImagePreviewPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Preview;

/**
 * Miniatura opisywanego pliku — treść prawego panelu.
 *
 * Przypadek użycia jest **własnością tego modułu**, choć przeglądarka ma bardzo
 * podobny. To nie jest powtórzenie przez nieuwagę, tylko reguła 15: moduł nigdy
 * nie sięga do innego modułu. Wspólne jest to, co należy do rdzenia —
 * `ImagePreviewPort`, `Preview` i komponent `ImageBox` — a różne to, skąd bierze
 * się ścieżka: tam z agregatu katalogu, tutaj z kontekstu sesji.
 *
 * Wynik zapamiętujemy pod ścieżką, bo klatka powstaje trzydzieści razy na
 * sekundę, a odczyt nagłówka pliku graficznego jest wejściem-wyjściem.
 */
final class PreviewEntryUseCase
{
    /**
     * Filtr wstępny po rozszerzeniu — decyzja bez dotykania dysku. Lista jest
     * ta sama, co w przeglądarce, bo opisuje **formaty**, a nie moduł.
     */
    private const IMAGE_EXTENSIONS = [
        'avif', 'bmp', 'gif', 'heic', 'heif', 'ico', 'jpeg', 'jpg',
        'png', 'ppm', 'svg', 'tga', 'tif', 'tiff', 'webp', 'xpm',
    ];

    private const MAXIMUM_FILE_BYTES = 32 * 1024 * 1024;

    private ?string $cachedPath = null;

    private ?Preview $cached = null;

    public function __construct(
        private readonly ImagePreviewPort $images,
        private readonly TranslatorPort $translator,
    ) {
    }

    /** `null` znaczy „nie ma czego pokazać” — panel zostaje pusty. */
    public function execute(?string $path, int $sizeInBytes): ?Preview
    {
        if ($path === null || !self::looksLikeImage($path)) {
            return null;
        }

        if ($this->cachedPath === $path && $this->cached !== null) {
            return $this->cached;
        }

        $this->cachedPath = $path;

        return $this->cached = $this->inspect($path, $sizeInBytes);
    }

    private function inspect(string $path, int $sizeInBytes): Preview
    {
        if ($sizeInBytes > self::MAXIMUM_FILE_BYTES) {
            return Preview::unavailable(
                $this->translator->translate('module.file-info.preview.tooLarge', [
                    'limit' => intdiv(self::MAXIMUM_FILE_BYTES, 1024 * 1024),
                ]),
            );
        }

        $metadata = $this->images->inspect($path);

        if ($metadata === null) {
            return Preview::unavailable($this->translator->translate('module.file-info.preview.unreadable'));
        }

        return Preview::ofImage($path, $this->caption($metadata));
    }

    private function caption(ImageMetadata $metadata): string
    {
        return sprintf('%d×%d  %s', $metadata->widthPixels, $metadata->heightPixels, $metadata->format);
    }

    private static function looksLikeImage(string $path): bool
    {
        $dot = strrpos($path, '.');

        if ($dot === false) {
            return false;
        }

        return in_array(strtolower(substr($path, $dot + 1)), self::IMAGE_EXTENSIONS, true);
    }
}
