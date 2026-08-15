<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Settings;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\FrameComposer;
use LightManager\Presentation\Cli\GameLoop;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Tests\Support\FixedViewport;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\RecordingRenderer;
use LightManager\Tests\Support\ResizableViewport;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\ScriptedTerminal;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Przebieg: **start aplikacji i dno stosu ekranów** (D42) oraz zmiana rozmiaru
 * okna w trakcie (krok 33).
 *
 * Jedyny przebieg katalogu, który idzie przez `GameLoop` ze `ScriptedTerminal`,
 * a nie przez sam `ScreenFixture` — i idzie tak z powodu, którego nie da się
 * obejść: **takt widać wyłącznie w pętli**. Dno stosu wybrane z konfiguracji,
 * pierwsza klatka i przebudowa po zmianie rozmiaru dzieją się przed pierwszym
 * klawiszem albo pomiędzy klatkami, więc ekran pytany po metodach nie
 * powiedziałby o nich nic.
 */
final class StartupFlowTest extends TestCase
{
    /** Takt podkręcony, żeby przebieg nie spał po 33 ms na iterację. */
    private const FAST_LOOP = 1000;

    private InMemoryDirectoryRepository $directories;

    protected function setUp(): void
    {
        $this->directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [Entry::directory('projekty'), Entry::file('notatka.txt', 2048)])
            ->add('/home/projekty', [Entry::file('plan.md', 120)]);
    }

    /** Bez wskazania w konfiguracji dno stosu bierze moduł ostatniej szansy. */
    public function testApplicationStartsOnTheBrowserAndDrawsItsFirstFrame(): void
    {
        $application = $this->application();
        $renderer = new RecordingRenderer();

        $this->loop($application, $renderer, new ScriptedTerminal([null, KeyPress::special(Key::F10, '')]))->run();

        self::assertSame('browser', $application->startup->screen->id());
        self::assertCount(1, $renderer->frames);
    }

    /**
     * Moduł wskazany w konfiguracji wygrywa z domyślnym — to jest cała treść
     * klucza `startupModule` (D42).
     */
    public function testConfiguredModuleTakesTheBottomOfTheStack(): void
    {
        $settings = new InMemorySettings(new Settings(startupModule: 'file-info'));

        self::assertSame('file-info', $this->application($settings)->startup->screen->id());
    }

    /**
     * Zmiana rozmiaru **z otwartym oknem nakładanym**: klatka po niej ma się
     * złożyć w nowym rozmiarze, a okno zostać na wierzchu. Przypadek z listy
     * kroku 33 — okno wyznacza swój prostokąt samo, więc rozmiar zmienia się
     * pod nim, a nie razem z nim.
     */
    public function testFrameFollowsTheWindowSizeWithAnOverlayOpen(): void
    {
        $application = $this->application();
        $renderer = new RecordingRenderer();
        $viewport = new ResizableViewport(30, 80);

        $application->input->handle(KeyPress::special(Key::F12, ''), $application->state, 0.0);

        $loop = new GameLoop(
            new ScriptedTerminal([null, null, KeyPress::special(Key::F10, '')]),
            new FrameComposer($renderer, $viewport, new StubTranslator(), InputHandler::globalBindings()),
            $application->screens,
            $application->input,
            $application->state,
            framesPerSecond: self::FAST_LOOP,
        );

        $viewport->resize(20, 60);
        $loop->run();

        $frame = $renderer->frames[0] ?? null;
        self::assertNotNull($frame);
        self::assertSame(60, $frame->planes[0]->bounds->columns, 'klatka w nowym rozmiarze');
        self::assertSame('command', $frame->planes[count($frame->planes) - 1]->id, 'okno zostaje na wierzchu');
    }

    private function application(?InMemorySettings $settings = null): ScreenFixture
    {
        return new ScreenFixture(
            $this->directories->get(new DirectoryPath('/home'), false),
            $this->directories,
            $settings ?? new InMemorySettings(),
        );
    }

    private function loop(ScreenFixture $application, RecordingRenderer $renderer, ScriptedTerminal $terminal): GameLoop
    {
        return new GameLoop(
            $terminal,
            new FrameComposer($renderer, new FixedViewport(30, 80), new StubTranslator(), InputHandler::globalBindings()),
            $application->screens,
            $application->input,
            $application->state,
            framesPerSecond: self::FAST_LOOP,
        );
    }
}
