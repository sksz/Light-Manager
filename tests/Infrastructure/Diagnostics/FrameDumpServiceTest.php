<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Diagnostics;

use Imagick;
use ImagickPixel;
use LightManager\Application\Ui\Frame;
use LightManager\Application\Ui\Plane;
use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Infrastructure\Diagnostics\FrameDumpService;
use LightManager\Infrastructure\Diagnostics\FrameImageGrabber;
use LightManager\Infrastructure\Diagnostics\FrameSerializer;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Zrzut z żywej aplikacji ma dać dowód, którego w kroku 29 trzeba było szukać
 * aparatem — więc sam potrzebuje dowodu, że powstaje i że powstaje z **tej**
 * klatki, o którą poproszono.
 */
final class FrameDumpServiceTest extends TestCase
{
    use ResetsSingletons;

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/lm-zrzut-' . bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
        $this->resetSingleton(FrameDumpService::class);
    }

    public function testNothingIsWrittenUntilSomebodyAsks(): void
    {
        $service = FrameDumpService::getInstance();

        self::assertFalse($service->isPending());
        self::assertNull($service->captureIfRequested($this->frame()));
        self::assertSame([], glob($this->directory . '/*'));
    }

    /**
     * Zamówienie obowiązuje **jedną** klatkę. Gdyby zostawało, każda następna
     * nadpisywałaby plik i zrzut przestałby opisywać chwilę, o którą chodziło.
     */
    public function testRequestCoversExactlyOneFrame(): void
    {
        $service = FrameDumpService::getInstance();
        $service->useGrabber($this->grabber());
        $service->request($this->directory . '/klatka');

        self::assertNotNull($service->captureIfRequested($this->frame()));
        self::assertFalse($service->isPending());
        self::assertNull($service->captureIfRequested($this->frame()));
    }

    public function testBothHalvesOfTheDumpLandOnDisk(): void
    {
        $service = FrameDumpService::getInstance();
        $service->useGrabber($this->grabber());
        $service->request($this->directory . '/klatka');

        $service->captureIfRequested($this->frame());

        self::assertFileExists($this->directory . '/klatka-prymitywy.txt');
        self::assertFileExists($this->directory . '/klatka.png');
        self::assertNull($service->lastProblem());
    }

    /**
     * Bez sposobu na obraz zrzut nadal ma sens: prymitywy odpowiadają na
     * pytanie „co aplikacja kazała narysować”. Brak drugiej połowy ma być
     * jednak **powiedziany**, a nie przemilczany.
     */
    public function testWithoutAGrabberThePrimitivesStillSurviveAndTheProblemIsRecorded(): void
    {
        $service = FrameDumpService::getInstance();
        $service->request($this->directory . '/klatka');

        $service->captureIfRequested($this->frame());

        self::assertFileExists($this->directory . '/klatka-prymitywy.txt');
        self::assertFileDoesNotExist($this->directory . '/klatka.png');
        self::assertSame($this->directory . '/klatka.png', $service->lastProblem());
    }

    /** Zapis prymitywów idzie podpisami — a te niosą wszystko, co wpływa na piksele. */
    public function testPrimitivesAreWrittenAsTheirSignatures(): void
    {
        $text = (new FrameSerializer())->toText($this->frame());

        self::assertStringContainsString('plane tresc', $text);
        self::assertStringContainsString('primitives=2', $text);
        self::assertStringContainsString('plik-0001.txt', $text);
    }

    private function frame(): Frame
    {
        $bounds = new Rect(0, 0, 4, 20);

        return new Frame([
            new Plane('tresc', $bounds, [
                new Bar($bounds, Role::Surface),
                new TextRun(1, 2, 'plik-0001.txt', Role::Text),
            ]),
        ]);
    }

    /** Sposób oddania obrazu bez Imagicka w tle — test sprawdza drogę, nie potok. */
    private function grabber(): FrameImageGrabber
    {
        return new class () implements FrameImageGrabber {
            public function imageOf(Frame $frame): Imagick
            {
                $image = new Imagick();
                $image->newImage(4, 4, new ImagickPixel('#1c1f26'), 'png');

                return $image;
            }
        };
    }
}
