<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use Imagick;
use LightManager\Infrastructure\Imagick\ImagickCapabilityService;

/**
 * Metryczka środowiska dopisywana do każdego wzorca.
 *
 * Bez niej plik z pomiarem jest liczbami bez kontekstu: ta sama konfiguracja na
 * innej wersji ImageMagicka albo z innym fontem daje inny wynik i nikt po roku
 * nie odtworzy, dlaczego. Wersja PHP i ImageMagicka są tu ważniejsze niż nazwa
 * maszyny — zmiana rozkładu potrafi przesunąć czas rasteryzacji o dziesiątki
 * procent.
 */
final class EnvironmentMetadata
{
    public function __construct(
        public readonly string $phpVersion,
        public readonly string $imageMagickVersion,
        public readonly string $font,
        /** Data i godzina w formacie ISO 8601. */
        public readonly string $recordedAt,
    ) {
    }

    public static function current(?string $requestedFont): self
    {
        return new self(
            PHP_VERSION,
            self::imageMagickVersion(),
            $requestedFont ?? ImagickCapabilityService::getInstance()->monospaceFont() ?? 'default',
            date('c'),
        );
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'php' => $this->phpVersion,
            'imageMagick' => $this->imageMagickVersion,
            'font' => $this->font,
            'recordedAt' => $this->recordedAt,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            JsonValue::string($data, 'php'),
            JsonValue::string($data, 'imageMagick'),
            JsonValue::string($data, 'font'),
            JsonValue::string($data, 'recordedAt'),
        );
    }

    private static function imageMagickVersion(): string
    {
        if (!extension_loaded('imagick')) {
            return 'unavailable';
        }

        return Imagick::getVersion()['versionString'];
    }
}
