<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\ImageMetadata;
use LightManager\Application\Port\ImagePreviewPort;

final class StubImagePreview implements ImagePreviewPort
{
    /** @var list<string> */
    public array $inspectedPaths = [];

    public function __construct(
        private readonly ?ImageMetadata $metadata = null,
    ) {
    }

    public static function withImage(int $width = 1920, int $height = 1080, string $format = 'JPEG'): self
    {
        return new self(new ImageMetadata($width, $height, $format));
    }

    /** Odpowiada „to nie jest obraz” na każde pytanie. */
    public static function unreadable(): self
    {
        return new self();
    }

    public function inspect(string $path): ?ImageMetadata
    {
        $this->inspectedPaths[] = $path;

        return $this->metadata;
    }
}
