<?php

declare(strict_types=1);

namespace LightManager\Tests\Domain\ValueObject;

use LightManager\Domain\Exception\InvalidPreviewException;
use LightManager\Domain\ValueObject\Preview;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PreviewTest extends TestCase
{
    public function testImagePreviewCarriesPathAndCaption(): void
    {
        $preview = Preview::ofImage('/katalog/foto.jpg', '1920×1080  JPEG');

        self::assertSame('/katalog/foto.jpg', $preview->path);
        self::assertSame('1920×1080  JPEG', $preview->caption);
        self::assertTrue($preview->isRenderable());
    }

    public function testUnavailablePreviewHasNothingToDraw(): void
    {
        $preview = Preview::unavailable('Nie udało się odczytać obrazu.');

        self::assertNull($preview->path);
        self::assertFalse($preview->isRenderable());
        self::assertSame('Nie udało się odczytać obrazu.', $preview->caption);
    }

    /** @return array<string, array{string}> */
    public static function blankCaptions(): array
    {
        return [
            'pusty' => [''],
            'same spacje' => ['   '],
            'sam odstęp pionowy' => ["\n"],
        ];
    }

    #[DataProvider('blankCaptions')]
    public function testRejectsBlankCaption(string $caption): void
    {
        $this->expectException(InvalidPreviewException::class);

        Preview::unavailable($caption);
    }

    /**
     * Renderer skleja ścieżkę wprost, bez rozwijania — ścieżka względna
     * wskazywałaby na katalog roboczy procesu, a nie na oglądany katalog.
     */
    public function testRejectsRelativePath(): void
    {
        $this->expectException(InvalidPreviewException::class);

        Preview::ofImage('foto.jpg', '1920×1080  JPEG');
    }

    public function testEqualityComparesPathAndCaption(): void
    {
        $preview = Preview::ofImage('/katalog/foto.jpg', '1920×1080  JPEG');

        self::assertTrue($preview->equals(Preview::ofImage('/katalog/foto.jpg', '1920×1080  JPEG')));
        self::assertFalse($preview->equals(Preview::ofImage('/katalog/inne.jpg', '1920×1080  JPEG')));
        self::assertFalse($preview->equals(Preview::ofImage('/katalog/foto.jpg', '800×600  PNG')));
        self::assertFalse($preview->equals(Preview::unavailable('1920×1080  JPEG')));
    }
}
