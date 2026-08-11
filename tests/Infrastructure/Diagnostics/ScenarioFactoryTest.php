<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Diagnostics;

use LightManager\Application\Dto\Language;
use LightManager\Application\Ui\Primitive\Bitmap;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Infrastructure\Diagnostics\BenchmarkOptions;
use LightManager\Infrastructure\Diagnostics\Scenario;
use LightManager\Infrastructure\Diagnostics\ScenarioFactory;
use LightManager\Infrastructure\Diagnostics\ScenarioFrame;
use LightManager\Tests\Support\PinsLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Determinizm scenariuszy jest warunkiem, bez którego porównanie z wzorcem nie
 * znaczy nic — a że treść klatki powstaje w kodzie, da się go sprawdzić testem.
 */
final class ScenarioFactoryTest extends TestCase
{
    use PinsLanguage;

    private ScenarioFactory $factory;

    protected function setUp(): void
    {
        $this->pinLanguage(Language::English);

        $this->factory = new ScenarioFactory(new BenchmarkOptions(), '/tmp/obraz.jpg');
    }

    protected function tearDown(): void
    {
        $this->unpinLanguage();
    }

    /** @return array<string, array{Scenario}> */
    public static function everyScenario(): array
    {
        $cases = [];

        foreach (Scenario::cases() as $scenario) {
            $cases[$scenario->value] = [$scenario];
        }

        return $cases;
    }

    #[DataProvider('everyScenario')]
    public function testTheSameScenarioIsBuiltIdenticallyEveryTime(Scenario $scenario): void
    {
        $first = $this->factory->build($scenario);
        $second = $this->factory->build($scenario);

        self::assertSame($first->frame->signature(), $second->frame->signature());
        self::assertSame([$first->rows, $first->columns], [$second->rows, $second->columns]);
    }

    /**
     * Płaszczyzna spodnia musi być osobna i pierwsza — enkoder zapamiętuje ją
     * między klatkami, więc treść położona na niej byłaby zmierzona raz, a potem
     * podawana z pamięci.
     */
    #[DataProvider('everyScenario')]
    public function testBaseLayerIsAlwaysSeparateAndFirst(Scenario $scenario): void
    {
        $built = $this->factory->build($scenario);

        self::assertSame('chrome', $built->frame->planes[0]->id);
        self::assertSame('content', $built->frame->planes[1]->id);
    }

    /**
     * Bez chromu nie ma ani jednej obwódki — inaczej różnica wobec scenariuszy
     * panelowych nie byłaby jego kosztem.
     */
    #[DataProvider('everyScenario')]
    public function testOnlyChromeScenariosDrawPanels(Scenario $scenario): void
    {
        $chrome = $this->factory->build($scenario)->frame->planes[0];

        if ($scenario->needsChrome()) {
            self::assertNotSame([], $chrome->primitives);

            return;
        }

        self::assertSame([], $chrome->primitives);
    }

    public function testEmptyScenarioDrawsNothingAtAll(): void
    {
        $built = $this->factory->build(Scenario::Empty);

        self::assertSame([], $built->frame->planes[0]->primitives);
        self::assertSame([], $built->frame->planes[1]->primitives);
    }

    public function testTextScenarioFillsTheListWithRows(): void
    {
        self::assertGreaterThan(20, count(self::ofType($this->factory->build(Scenario::Text), TextRun::class)));
    }

    public function testScrollbarScenarioCarriesAScrollbar(): void
    {
        self::assertNotSame([], self::ofType($this->factory->build(Scenario::Scrollbar), Scrollbar::class));
    }

    public function testSelectionScenarioMarksEveryRow(): void
    {
        self::assertGreaterThan(20, count(self::ofType($this->factory->build(Scenario::Selection), RoundRect::class)));
    }

    public function testThumbnailScenarioCarriesTheImagePath(): void
    {
        $bitmaps = self::ofType($this->factory->build(Scenario::Thumbnail), Bitmap::class);

        self::assertCount(1, $bitmaps);
        self::assertInstanceOf(Bitmap::class, $bitmaps[0]);
        self::assertSame('/tmp/obraz.jpg', $bitmaps[0]->path);
    }

    public function testThumbnailScenarioWithoutAnImageStillReservesTheBox(): void
    {
        $bitmaps = self::ofType(
            (new ScenarioFactory(new BenchmarkOptions()))->build(Scenario::Thumbnail),
            Bitmap::class,
        );

        self::assertCount(1, $bitmaps);
        self::assertInstanceOf(Bitmap::class, $bitmaps[0]);
        self::assertNull($bitmaps[0]->path);
    }

    /**
     * Scenariusz paska postępu mierzy **oba tryby naraz** — inaczej nie mierzy
     * tego, po co powstał: tryb z liczbą i tryb bez niej rysują inną liczbę
     * napisów, bo ten pierwszy tnie tekst na krawędzi wypełnienia.
     */
    public function testProgressScenarioCarriesBothModes(): void
    {
        $texts = [];

        foreach (self::ofType($this->factory->build(Scenario::Progress), TextRun::class) as $run) {
            self::assertInstanceOf(TextRun::class, $run);
            $texts[] = $run->text;
        }

        $joined = implode("\n", $texts);

        self::assertStringContainsString('%', $joined, 'postęp znany pokazuje liczbę');
        self::assertStringContainsString('licze rozmiar', $joined, 'a nieznany sam napis');
    }

    /** Wędrujące wypełnienie stoi w miejscu wziętym z wiersza, nie z zegara. */
    public function testProgressScenarioDoesNotDependOnTheWallClock(): void
    {
        $first = $this->factory->build(Scenario::Progress);
        usleep(1000);
        $second = $this->factory->build(Scenario::Progress);

        self::assertSame($first->frame->signature(), $second->frame->signature());
    }

    public function testPopupScenarioAddsAThirdPlane(): void
    {
        $built = $this->factory->build(Scenario::Popup);

        self::assertCount(3, $built->frame->planes);
        self::assertSame('modal', $built->frame->planes[2]->id);
    }

    public function testGridTravelsWithTheFrame(): void
    {
        $options = new BenchmarkOptions();
        $built = $this->factory->build(Scenario::ChromeWithText);

        self::assertSame($options->rows, $built->rows);
        self::assertSame($options->columns, $built->columns);
    }

    /**
     * @param class-string $type
     *
     * @return list<Primitive>
     */
    private static function ofType(ScenarioFrame $built, string $type): array
    {
        $found = [];

        foreach ($built->frame->planes as $plane) {
            foreach ($plane->primitives as $primitive) {
                if ($primitive instanceof $type) {
                    $found[] = $primitive;
                }
            }
        }

        return $found;
    }
}
