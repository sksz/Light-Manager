<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Application\UseCase;

use LightManager\Application\Dto\ImageMetadata;
use LightManager\Domain\ValueObject\Preview;
use LightManager\Module\Browser\Application\UseCase\PreviewSelectedEntryUseCase;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\StubImagePreview;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PreviewSelectedEntryUseCaseTest extends TestCase
{
    public function testDescribesImageWithDimensionsAndFormat(): void
    {
        $preview = $this->preview(
            StubImagePreview::withImage(1920, 1080, 'JPEG'),
            Entry::file('foto.jpg', 1024),
        );

        self::assertNotNull($preview);
        self::assertSame('/katalog/foto.jpg', $preview->path);
        self::assertSame('1920×1080  JPEG', $preview->caption);
    }

    public function testDirectoryHasNoPreview(): void
    {
        self::assertNull($this->preview(StubImagePreview::withImage(), Entry::directory('podkatalog')));
    }

    /** @return array<string, array{string}> */
    public static function namesWithoutImageExtension(): array
    {
        return [
            'tekst' => ['notatka.txt'],
            'archiwum' => ['paczka.tar.gz'],
            'bez rozszerzenia' => ['README'],
            'sama kropka na początku' => ['.bashrc'],
            'rozszerzenie w środku nazwy' => ['foto.jpg.bak'],
        ];
    }

    /** Filtr wstępny ma odsiać plik bez otwierania go — port nie może zostać zapytany. */
    #[DataProvider('namesWithoutImageExtension')]
    public function testNonImageIsRejectedWithoutTouchingThePort(string $name): void
    {
        $images = StubImagePreview::withImage();

        self::assertNull($this->preview($images, Entry::file($name, 1024)));
        self::assertSame([], $images->inspectedPaths);
    }

    /** @return array<string, array{string}> */
    public static function imageNames(): array
    {
        return [
            'małe litery' => ['foto.jpg'],
            'wielkie litery' => ['FOTO.JPG'],
            'mieszane' => ['Foto.JpEg'],
            'png' => ['zrzut.png'],
            'webp' => ['obrazek.webp'],
        ];
    }

    #[DataProvider('imageNames')]
    public function testRecognisesImageExtensionRegardlessOfCase(string $name): void
    {
        $preview = $this->preview(StubImagePreview::withImage(), Entry::file($name, 1024));

        self::assertNotNull($preview);
        self::assertTrue($preview->isRenderable());
    }

    public function testUnreadableImageGivesReasonInsteadOfThumbnail(): void
    {
        $preview = $this->preview(StubImagePreview::unreadable(), Entry::file('uszkodzony.jpg', 1024));

        self::assertNotNull($preview);
        self::assertFalse($preview->isRenderable());
        self::assertSame('module.browser.preview.unreadable', $preview->caption);
    }

    /** Limit bajtów działa przed portem — za duży plik nie ma być nawet otwierany. */
    public function testTooLargeFileIsRejectedBeforeReadingHeader(): void
    {
        $images = StubImagePreview::withImage();

        $preview = $this->preview($images, Entry::file('ogromne.jpg', 33 * 1024 * 1024));

        self::assertNotNull($preview);
        self::assertFalse($preview->isRenderable());
        self::assertSame('module.browser.preview.tooLarge(limit=32)', $preview->caption);
        self::assertSame([], $images->inspectedPaths);
    }

    public function testTooManyPixelsIsRejectedAfterReadingHeader(): void
    {
        $preview = $this->preview(
            StubImagePreview::withImage(20000, 20000, 'TIFF'),
            Entry::file('mapa.tiff', 1024),
        );

        self::assertNotNull($preview);
        self::assertFalse($preview->isRenderable());
        self::assertSame('module.browser.preview.tooManyPixels(dimensions=20000×20000,limit=50)', $preview->caption);
    }

    public function testHeaderIsReadOnceWhileSelectionStaysPut(): void
    {
        $images = StubImagePreview::withImage();
        $useCase = new PreviewSelectedEntryUseCase($images, new StubTranslator());
        $directory = $this->directoryWith(Entry::file('foto.jpg', 1024));

        $first = $useCase->execute($directory);
        $second = $useCase->execute($directory);

        self::assertCount(1, $images->inspectedPaths);
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertTrue($first->equals($second));
    }

    public function testMovingSelectionReadsTheNewFile(): void
    {
        $images = StubImagePreview::withImage();
        $useCase = new PreviewSelectedEntryUseCase($images, new StubTranslator());
        $directory = new Directory(new DirectoryPath('/katalog'), [
            Entry::file('pierwsze.jpg', 1024),
            Entry::file('drugie.png', 2048),
        ]);

        $useCase->execute($directory);
        $directory->moveSelectionDown();
        $useCase->execute($directory);

        self::assertSame(
            ['/katalog/pierwsze.jpg', '/katalog/drugie.png'],
            $images->inspectedPaths,
        );
    }

    public function testEmptyDirectoryHasNoPreview(): void
    {
        $useCase = new PreviewSelectedEntryUseCase(StubImagePreview::withImage(), new StubTranslator());

        self::assertNull($useCase->execute(new Directory(new DirectoryPath('/pusty'), [])));
    }

    public function testMetadataCountsPixels(): void
    {
        self::assertSame(2073600, (new ImageMetadata(1920, 1080, 'JPEG'))->pixels());
    }

    private function preview(StubImagePreview $images, Entry $entry): ?Preview
    {
        return (new PreviewSelectedEntryUseCase($images, new StubTranslator()))->execute($this->directoryWith($entry));
    }

    private function directoryWith(Entry $entry): Directory
    {
        return new Directory(new DirectoryPath('/katalog'), [$entry]);
    }
}
