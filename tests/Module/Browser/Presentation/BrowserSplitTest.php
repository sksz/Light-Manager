<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Presentation;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Application\BrowserSettings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\FrameComposer;
use LightManager\Presentation\Ui\Component\Split;
use LightManager\Tests\Support\FixedViewport;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\RecordingRenderer;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Podział ekranu widziany od strony użytkownika: dwa katalogi, jedno ognisko
 * i klatka, która bez podziału wygląda **co do znaku** jak przed krokiem 24.
 *
 * Test patrzy na klatkę i na klawisze, a nie na klasy: to jedyny sposób, żeby
 * złapać błąd, który tu grozi naprawdę — rozjazd między tym, kto dostaje klawisz,
 * a tym, co widać na ekranie.
 */
final class BrowserSplitTest extends TestCase
{
    private ScreenFixture $app;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [Entry::directory('dokumenty'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', [Entry::file('umowa.pdf', 2048)]);

        $this->app = new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
    }

    /** Zgodność wsteczna: bez podziału oprawę rysuje rdzeń, tak jak dotąd. */
    public function testWithoutTheSplitTheCoreStillFramesTheMiddleZone(): void
    {
        $frame = $this->render(100, 30);

        self::assertContains('module.browser.zone.files', self::textsOf($frame['chrome']));
        self::assertSame(
            [],
            self::framesOf($frame['content']),
            'ekran nie rysuje ani jednej obwódki — cała oprawa leży w zapamiętanej płaszczyźnie',
        );
    }

    /**
     * Przy podziale rdzeń oddaje strefę środkową ekranowi — ale **obwódki i tak
     * lądują w płaszczyźnie spodniej**, a nie w treści.
     *
     * Druga asercja jest ważniejsza od pierwszej i pilnuje pomiaru, a nie wyglądu:
     * obrys z wygładzaniem kosztuje kilkanaście milisekund, więc obwódka
     * przeniesiona do treści powstawałaby trzydzieści razy na sekundę. Krok 24
     * zmierzył to i wynosiło 27 ms na dwie ramki — połowa budżetu klatki.
     */
    public function testWithTheSplitTheScreenFramesBothPanesItself(): void
    {
        $this->enableSplit();
        $frame = $this->render(100, 30);

        self::assertNotContains('module.browser.zone.files', self::textsOf($frame['chrome']));
        self::assertGreaterThanOrEqual(2, count(self::framesOf($frame['chrome'])));
        self::assertSame([], self::framesOf($frame['content']), 'oprawa nie ma prawa trafić do treści');
    }

    /** Etykietą panelu jest jego ścieżka — inaczej katalogu panelu nieczynnego nie widać nigdzie. */
    public function testEachPaneCarriesItsOwnPathInTheFrameLabel(): void
    {
        $this->enableSplit();
        $this->app->browser->handle(new KeyPress(Key::Tab, "\t"));
        $this->app->browser->handle(new KeyPress(Key::Enter, "\r"));

        $texts = self::textsOf($this->render(100, 30)['chrome']);

        self::assertContains('/home', $texts);
        self::assertContains('/home/dokumenty', $texts, 'drugi panel wszedł do katalogu, pierwszy został');
    }

    public function testKeysGoToTheFocusedPaneOnly(): void
    {
        $this->enableSplit();
        $this->app->browser->handle(new KeyPress(Key::Tab, "\t"));
        $this->app->browser->handle(new KeyPress(Key::ArrowDown, ''));

        // Ścieżka u góry należy do panelu z ogniskiem, a jej końcówka niesie numer
        // zaznaczenia — czyli dokładnie to, co ruch strzałką miał zmienić.
        self::assertStringContainsString('2/2', $this->headerText());

        $this->app->browser->handle(new KeyPress(Key::Tab, "\t"));

        self::assertStringContainsString('1/2', $this->headerText(), 'pierwszy panel stoi tam, gdzie stał');
    }

    /** Kontekst sesji ma jednego wydawcę i jest nim panel z ogniskiem. */
    public function testMovingFocusRepublishesTheSessionContext(): void
    {
        $this->enableSplit();
        $this->app->browser->handle(new KeyPress(Key::Tab, "\t"));
        $this->app->browser->handle(new KeyPress(Key::ArrowDown, ''));

        self::assertSame('notatka.txt', $this->app->state->context()->selection);

        $this->app->browser->handle(new KeyPress(Key::Tab, "\t"));

        self::assertSame(
            'dokumenty',
            $this->app->state->context()->selection,
            'powrót ogniska ogłasza zaznaczenie pierwszego panelu',
        );
    }

    /** Poniżej progu szerokości podziału nie ma, choć ustawienie jest włączone. */
    public function testBelowTheWidthThresholdNothingSplits(): void
    {
        $this->enableSplit();
        $frame = $this->render(Split::MINIMUM_COLUMNS - 1, 30);

        self::assertContains('module.browser.zone.files', self::textsOf($frame['chrome']));
    }

    /**
     * `Tab` przy jednym panelu nie ma dokąd przenieść ogniska — i nie przenosi.
     * Gdyby przenosił, klawisze zaczęłyby trafiać do listy, której nie widać.
     */
    public function testTabDoesNothingWhenTheSplitIsOff(): void
    {
        $this->app->browser->handle(new KeyPress(Key::Tab, "\t"));
        $this->app->browser->handle(new KeyPress(Key::ArrowDown, ''));

        self::assertStringContainsString('2/2', $this->headerText(), 'strzałka ruszyła jedyny widoczny panel');
    }

    /** Wpisy ukryte są ustawieniem modułu, więc dotyczą obu paneli naraz. */
    public function testHiddenEntriesToggleReachesBothPanes(): void
    {
        $this->enableSplit();
        $this->app->browser->handle(KeyPress::character('.'));

        self::assertTrue(BrowserSettings::showHidden($this->app->state->settings()));

        $texts = self::textsOf($this->render(100, 30)['content']);
        $marks = array_filter($texts, static fn (string $text): bool => str_contains($text, 'ukryte'));

        self::assertSame([], $marks, 'znacznik wpisów ukrytych należy do górnego pasa, nie do paneli');
    }

    /** Klawisz ogniska pojawia się w spisie dopiero wraz z podziałem. */
    public function testFocusKeyIsAnnouncedOnlyWithTheSplitOn(): void
    {
        self::assertSame([], self::tabBindings($this->app->browser->bindings()));

        $this->enableSplit();

        self::assertCount(1, self::tabBindings($this->app->browser->bindings()));
    }

    /** Treść górnego pasa — należy do panelu z ogniskiem, więc mówi, gdzie stoi kursor. */
    private function headerText(): string
    {
        $header = $this->app->browser->header();

        self::assertNotNull($header);

        return implode('', self::textsOf($header->content->draw(new Rect(0, 0, 1, 80))));
    }

    private function enableSplit(): void
    {
        $this->app->state->applySettings($this->app->state->settings()->withModuleValue(
            BrowserSettings::ID,
            BrowserSettings::SPLIT,
            true,
        ));
    }

    /** @return array<string, list<Primitive>> płaszczyzny ostatniej klatki po identyfikatorze */
    private function render(int $columns, int $rows): array
    {
        $renderer = new RecordingRenderer();
        (new FrameComposer($renderer, new FixedViewport($rows, $columns), new StubTranslator()))
            ->render($this->app->browser, $this->app->state);

        $planes = [];
        $frame = $renderer->last();

        self::assertNotNull($frame);

        foreach ($frame->planes as $plane) {
            $planes[$plane->id] = $plane->primitives;
        }

        return $planes;
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<string>
     */
    private static function textsOf(array $primitives): array
    {
        $texts = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<RoundRect> obwódki, czyli prostokąty bez wypełnienia
     */
    private static function framesOf(array $primitives): array
    {
        return array_values(array_filter(
            $primitives,
            static fn (Primitive $primitive): bool => $primitive instanceof RoundRect && $primitive->stroke !== null,
        ));
    }

    /**
     * @param list<\LightManager\Presentation\Ui\KeyBinding> $bindings
     *
     * @return list<\LightManager\Presentation\Ui\KeyBinding>
     */
    private static function tabBindings(array $bindings): array
    {
        return array_values(array_filter(
            $bindings,
            static fn ($binding): bool => in_array(Key::Tab, $binding->keys, true),
        ));
    }
}
