<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

use LightManager\Application\Dto\ImageMetadata;

/**
 * Odczyt nagłówka pliku graficznego.
 *
 * Kontrakt celowo mówi tylko o metadanych, nie o pikselach: warstwa aplikacji
 * decyduje, czy podgląd w ogóle powstanie, a samo skalowanie należy do
 * renderera, który jako jedyny zna rozmiar miejsca na ekranie.
 */
interface ImagePreviewPort
{
    /** `null`, gdy plik nie jest obrazem albo nie da się odczytać jego nagłówka. */
    public function inspect(string $path): ?ImageMetadata;
}
