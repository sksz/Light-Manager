<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Glfw;

use GL\VectorGraphics\VGImage;
use LightManager\Infrastructure\Glfw\VgTextureCache;
use PHPUnit\Framework\TestCase;

/**
 * Pamięć tekstur bez kontekstu GL: ładowanie jest wstrzykiwane, więc testowalne
 * jest to, co w tej klasie naprawdę mieszka — **kiedy loader jest wołany, a
 * kiedy nie**. Sama tekstura to zadanie sterownika, nie tej klasy.
 *
 * Kryterium kroku 35 brzmi „druga klatka z tą samą miniaturą nie dekoduje jej
 * ponownie” i dokładnie to sprawdza pierwszy test.
 */
final class VgTextureCacheTest extends TestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'lm-texture-');
        file_put_contents($this->file, 'x');
    }

    protected function tearDown(): void
    {
        if ($this->file !== '' && is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testSecondFrameWithTheSameImageDoesNotDecodeItAgain(): void
    {
        $calls = 0;
        $cache = new VgTextureCache(function (string $path) use (&$calls): array {
            ++$calls;

            return $this->entry();
        });

        $first = $cache->textureFor($this->file);
        $second = $cache->textureFor($this->file);

        self::assertSame(1, $calls);
        self::assertSame($first, $second);
    }

    /**
     * Plik nieczytelny też jest wpisem — inaczej pętla próbowałaby go
     * dekodować trzydzieści razy na sekundę.
     */
    public function testUnreadableFileIsRememberedAsWell(): void
    {
        $calls = 0;
        $cache = new VgTextureCache(static function (string $path) use (&$calls): ?array {
            ++$calls;

            return null;
        });

        self::assertNull($cache->textureFor($this->file));
        self::assertNull($cache->textureFor($this->file));
        self::assertSame(1, $calls);
    }

    /** Podmieniony plik pod tą samą nazwą dostaje świeżą teksturę — klucz niesie czas i rozmiar. */
    public function testChangedFileIsDecodedAgain(): void
    {
        $calls = 0;
        $cache = new VgTextureCache(function (string $path) use (&$calls): array {
            ++$calls;

            return $this->entry();
        });

        $cache->textureFor($this->file);

        file_put_contents($this->file, 'znacznie dłuższa zawartość');
        touch($this->file, time() + 10);
        clearstatcache();

        $cache->textureFor($this->file);

        self::assertSame(2, $calls);
    }

    /**
     * Limit wpisów: tekstura potrafi ważyć megabajty, więc pamięć nie ma prawa
     * rosnąć w nieskończoność przy przewijaniu katalogu pełnego zdjęć.
     */
    public function testOldestEntryIsEvictedOnceTheLimitIsReached(): void
    {
        $calls = [];
        $cache = new VgTextureCache(function (string $path) use (&$calls): array {
            $calls[] = $path;

            return $this->entry();
        });

        $paths = [];

        for ($index = 0; $index < 40; ++$index) {
            $path = $this->file . '-' . $index;
            $paths[] = $path;
            $cache->textureFor($path);
        }

        // Pierwszy wypadł z pamięci, ostatni w niej został.
        $cache->textureFor($paths[0]);
        $cache->textureFor($paths[39]);

        self::assertSame(41, count($calls));
        self::assertSame($paths[0], $calls[40]);
    }

    /** @return array{image: VGImage, width: int, height: int} */
    private function entry(): array
    {
        return ['image' => $this->createStub(VGImage::class), 'width' => 64, 'height' => 48];
    }
}
