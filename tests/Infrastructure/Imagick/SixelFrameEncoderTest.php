<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Imagick;

use Imagick;
use LightManager\Application\Ui\Corner;
use LightManager\Application\Ui\Frame;
use LightManager\Application\Ui\Plane;
use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Bitmap;
use LightManager\Application\Ui\Primitive\CornerBrackets;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Primitive\Weight;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Infrastructure\Imagick\ImagickCapabilityService;
use LightManager\Infrastructure\Imagick\SixelFrameEncoder;
use LightManager\Infrastructure\Imagick\ThumbnailService;
use LightManager\Infrastructure\Rendering\RenderingOptions;
use LightManager\Infrastructure\Rendering\Theme;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Enkoder to jedyna część potoku renderowania, którą da się sprawdzić bez
 * terminala — wystarczy mu Imagick.
 *
 * Sprawdzamy strukturalnie: każdy kształt musi zmieniać wynikowe bajty, a ten
 * sam komplet kształtów musi dawać bajt w bajt to samo. Wygląd (kształt łuków,
 * grubość obwódki) weryfikowany jest oczami pod XTermem, bo test bajtów i tak
 * by go nie opisał.
 *
 * Od kroku 18 enkoder nie wie, co przedstawia klatka, więc i test nie mówi już
 * o liście plików ani o pasku stanu — mówi o prymitywach.
 */
final class SixelFrameEncoderTest extends TestCase
{
    use ResetsSingletons;

    private const COLUMNS = 40;

    private const ROWS = 12;

    private const WIDTH = 320;

    private const HEIGHT = 240;

    private SixelFrameEncoder $encoder;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $this->encoder = new SixelFrameEncoder();
        $this->temporaryFiles = [];
    }

    protected function tearDown(): void
    {
        $this->resetSingleton(ImagickCapabilityService::class);

        // Miniatury są pamiętane między wywołaniami — bez zerowania kolejny
        // test dostałby obrazek poprzedniego.
        $this->resetSingleton(ThumbnailService::class);

        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }
    }

    /** @param list<Primitive> $content */
    private function encode(array $content, ?RenderingOptions $options = null): string
    {
        $window = new Rect(0, 0, self::ROWS, self::COLUMNS);

        return $this->encoder->encode(
            new Frame([new Plane('chrome', $window, []), new Plane('content', $window, $content)]),
            $options ?? self::options(),
            self::WIDTH,
            self::HEIGHT,
            self::ROWS,
            self::COLUMNS,
        );
    }

    /**
     * Płaszczyzna nieprzezroczysta zakrywa to, co pod nią.
     *
     * Bez tego okno komend — złożone z `Panel`a, czyli z samej obwódki —
     * przepuszczało treść spod spodu, łącznie z miniaturą w pasie podglądu.
     */
    public function testOpaquePlaneCoversWhatIsBeneathIt(): void
    {
        $window = new Rect(0, 0, self::ROWS, self::COLUMNS);
        $covered = new Rect(2, 0, 2, self::COLUMNS);

        $canvas = $this->encoder->drawCanvas(
            new Frame([
                new Plane('chrome', $window, []),
                new Plane('content', $window, [
                    new RoundRect($covered, Role::Accent, null, Corner::Soft),
                ]),
                new Plane('overlay', $covered, [], opaque: true),
            ]),
            self::options(),
            self::WIDTH,
            self::HEIGHT,
            self::ROWS,
            self::COLUMNS,
        );

        $pixel = $canvas->getImagePixelColor(intdiv(self::WIDTH, 2), intdiv(self::HEIGHT * 5, self::ROWS * 2));

        self::assertSame(
            strtolower(self::options()->theme->background),
            self::hexOf($pixel),
            'pod nieprzezroczystą płaszczyzną zostaje samo tło motywu',
        );

        $canvas->clear();
    }

    private static function hexOf(\ImagickPixel $pixel): string
    {
        /** @var array{r: int, g: int, b: int, a: int} $color */
        $color = $pixel->getColor();

        return sprintf('#%02x%02x%02x', $color['r'], $color['g'], $color['b']);
    }

    /** Pamięć podręczna płaszczyzny nie ma prawa pomylić zakrywającej z przezroczystą. */
    public function testOpacityChangesThePlaneSignature(): void
    {
        $bounds = new Rect(1, 1, 3, 10);

        self::assertNotSame(
            (new Plane('overlay', $bounds, []))->signature(),
            (new Plane('overlay', $bounds, [], opaque: true))->signature(),
        );
    }

    public function testProducesSixelDataStream(): void
    {
        $blob = $this->encode([new TextRun(1, 2, 'alfa.txt', Role::Text)]);

        self::assertStringStartsWith("\eP", $blob);
        self::assertStringEndsWith("\e\\", $blob);
    }

    public function testDeclaresRequestedCanvasSize(): void
    {
        self::assertStringContainsString(
            '"1;1;' . self::WIDTH . ';' . self::HEIGHT,
            $this->encode([]),
        );
    }

    public function testSameContentProducesIdenticalOutput(): void
    {
        $content = [new TextRun(1, 2, 'alfa.txt', Role::Text)];

        self::assertSame($this->encode($content), $this->encode($content));
    }

    /** @return array<string, array{list<Primitive>}> */
    public static function primitives(): array
    {
        $line = new Rect(3, 2, 1, 36);

        return [
            'napis' => [[new TextRun(1, 2, 'alfa.txt', Role::Text)]],
            'obwódka' => [[new RoundRect(new Rect(0, 1, 5, 38), null, Role::Border, Corner::Round)]],
            'wypełnienie' => [[new RoundRect($line, Role::Selection, null, Corner::Soft)]],
            'nawiasy narożne' => [[new CornerBrackets(new Rect(0, 1, 5, 38), Role::Accent, Corner::Round)]],
            'włos' => [[new Bar(new Rect(4, 10, 1, 1), Role::Border, Weight::Hairline)]],
            'krawędź' => [[new Bar($line, Role::Accent, Weight::Edge)]],
            'suwak' => [[new Scrollbar(new Rect(1, 37, 8, 1), new ScrollPosition(0, 4, 20))]],
        ];
    }

    /** @param list<Primitive> $content */
    #[DataProvider('primitives')]
    public function testEveryPrimitiveLeavesATraceOnTheCanvas(array $content): void
    {
        self::assertNotSame($this->encode([]), $this->encode($content));
    }

    public function testTextColourChangesTheFrame(): void
    {
        self::assertNotSame(
            $this->encode([new TextRun(1, 2, 'alfa.txt', Role::Text)]),
            $this->encode([new TextRun(1, 2, 'alfa.txt', Role::Accent)]),
        );
    }

    public function testTextPositionChangesTheFrame(): void
    {
        self::assertNotSame(
            $this->encode([new TextRun(1, 2, 'alfa.txt', Role::Text)]),
            $this->encode([new TextRun(2, 2, 'alfa.txt', Role::Text)]),
        );
    }

    public function testScrollbarPositionChangesTheFrame(): void
    {
        $bounds = new Rect(1, 37, 8, 1);

        self::assertNotSame(
            $this->encode([new Scrollbar($bounds, new ScrollPosition(0, 4, 20))]),
            $this->encode([new Scrollbar($bounds, new ScrollPosition(12, 4, 20))]),
        );
    }

    public function testScrollbarDisappearsWhenEverythingFits(): void
    {
        self::assertSame(
            $this->encode([]),
            $this->encode([new Scrollbar(new Rect(1, 37, 8, 1), new ScrollPosition(0, 20, 20))]),
        );
    }

    public function testPrimitivesOutsideTheGridDoNotBreakTheEncoder(): void
    {
        $blob = $this->encode([
            new TextRun(self::ROWS + 5, 2, 'poza siatką', Role::Text),
            new RoundRect(new Rect(self::ROWS + 2, 1, 3, 10), null, Role::Border, Corner::Round),
        ]);

        self::assertStringStartsWith("\eP", $blob);
    }

    public function testTheBaseLayerIsRememberedBetweenFrames(): void
    {
        $window = new Rect(0, 0, self::ROWS, self::COLUMNS);
        $chrome = new Plane('chrome', $window, [new RoundRect(new Rect(0, 1, 5, 38), null, Role::Border, Corner::Round)]);
        $options = self::options();

        $first = $this->encoder->encode(
            new Frame([$chrome, new Plane('content', $window, [])]),
            $options,
            self::WIDTH,
            self::HEIGHT,
            self::ROWS,
            self::COLUMNS,
        );

        $second = $this->encoder->encode(
            new Frame([$chrome, new Plane('content', $window, [])]),
            $options,
            self::WIDTH,
            self::HEIGHT,
            self::ROWS,
            self::COLUMNS,
        );

        self::assertSame($first, $second, 'zapamiętana oprawa musi dawać tę samą klatkę');
    }

    public function testChangedBaseLayerRefreshesTheMemory(): void
    {
        $window = new Rect(0, 0, self::ROWS, self::COLUMNS);
        $options = self::options();

        $withBorder = $this->encoder->encode(
            new Frame([new Plane('chrome', $window, [
                new RoundRect(new Rect(0, 1, 5, 38), null, Role::Border, Corner::Round),
            ])]),
            $options,
            self::WIDTH,
            self::HEIGHT,
            self::ROWS,
            self::COLUMNS,
        );

        $bare = $this->encoder->encode(
            new Frame([new Plane('chrome', $window, [])]),
            $options,
            self::WIDTH,
            self::HEIGHT,
            self::ROWS,
            self::COLUMNS,
        );

        self::assertNotSame($withBorder, $bare);
    }

    public function testMissingImageFallsBackToTheEmptyBox(): void
    {
        $withBox = $this->encode([new Bitmap(new Rect(6, 2, 4, 12), null, 'brak podglądu')]);

        self::assertNotSame($this->encode([]), $withBox);
        self::assertFalse($this->encoder->canvasCarriesBitmap());
    }

    public function testRealImageLandsOnTheCanvasAndSwitchesThePalette(): void
    {
        $path = $this->createImage();

        $this->encode([new Bitmap(new Rect(6, 2, 4, 12), $path, '32 × 32 · PNG')]);

        self::assertTrue($this->encoder->canvasCarriesBitmap());
    }

    /**
     * Kryterium poprawności palety z D27 — „każda rola motywu ma w klatce
     * odległość 0” — obowiązuje **także wtedy, gdy w klatce leży miniatura**.
     *
     * Zanim paleta stała się hybrydowa, klatka z podglądem szła kwantyzacją
     * adaptacyjną i kolory interfejsu wędrowały za zawartością zdjęcia: akcent
     * Grafitu wychodził na `#b15f0d`, a tło na `#020203`. Na ekranie wyglądało
     * to tak, jakby najechanie kursorem na plik graficzny przełączało aplikację
     * na inny motyw.
     */
    public function testThemeColoursSurviveAFrameCarryingAThumbnail(): void
    {
        $theme = Theme::grafit();
        $window = new Rect(0, 0, self::ROWS, self::COLUMNS);

        $canvas = $this->encoder->drawCanvas(
            new Frame([new Plane('chrome', $window, []), new Plane('content', $window, [
                new Bar(new Rect(1, 2, 1, 10), Role::Accent, Weight::Fill),
                new Bar(new Rect(3, 2, 1, 10), Role::Selection, Weight::Fill),
                new Bitmap(new Rect(6, 2, 4, 12), $this->createImage(), '32 × 32 · PNG'),
            ])]),
            self::options($theme),
            self::WIDTH,
            self::HEIGHT,
            self::ROWS,
            self::COLUMNS,
        );

        try {
            self::assertTrue($this->encoder->canvasCarriesBitmap(), 'miniatura musi trafić na płótno');

            $this->encoder->quantizeCanvas($canvas, true);

            // Środek pasków i róg płótna: wiersz ma 20 px, kolumna 8 px.
            self::assertSame($theme->accent, self::pixelAt($canvas, 40, 30), 'akcent');
            self::assertSame($theme->selection, self::pixelAt($canvas, 40, 70), 'tło zaznaczenia');
            self::assertSame($theme->background, self::pixelAt($canvas, 0, 0), 'tło klatki');
        } finally {
            $canvas->clear();
        }
    }

    private static function pixelAt(Imagick $canvas, int $x, int $y): string
    {
        /** @var array{r: int, g: int, b: int} $channels */
        $channels = $canvas->getImagePixelColor($x, $y)->getColor();

        return sprintf('#%02x%02x%02x', $channels['r'], $channels['g'], $channels['b']);
    }

    /** @return array<string, array{RenderingOptions}> */
    public static function renderingOptions(): array
    {
        return [
            'inny motyw' => [self::options(Theme::papier())],
            'wygładzanie tekstu' => [self::options(textAntialias: true)],
            'mniejsza paleta' => [self::options(paletteColors: 16)],
        ];
    }

    #[DataProvider('renderingOptions')]
    public function testRenderingOptionsReachTheCanvas(RenderingOptions $altered): void
    {
        $content = [new TextRun(1, 2, 'alfa.txt', Role::Text)];

        self::assertNotSame($this->encode($content), $this->encode($content, $altered));
    }

    private static function options(
        ?Theme $theme = null,
        bool $textAntialias = false,
        int $paletteColors = 64,
    ): RenderingOptions {
        return new RenderingOptions($theme ?? Theme::grafit(), $textAntialias, true, $paletteColors);
    }

    private function createImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'lm-') . '.png';
        $this->temporaryFiles[] = $path;

        $image = new Imagick();
        $image->newPseudoImage(32, 32, 'gradient:red-blue');
        $image->setImageFormat('png');
        $image->writeImage($path);
        $image->clear();

        return $path;
    }
}
