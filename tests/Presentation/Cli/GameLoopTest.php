<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\FrameComposer;
use LightManager\Presentation\Cli\GameLoop;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Presentation\Ui\HudLayout;
use LightManager\Tests\Support\FixedViewport;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\RecordingRenderer;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\ScriptedTerminal;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class GameLoopTest extends TestCase
{
    /** Takt podkręcony, żeby testy nie spały po 33 ms na iterację. */
    private const TEST_FRAMES_PER_SECOND = 1000;

    private ScreenFixture $app;

    private RecordingRenderer $renderer;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('home')])
            ->add('/home', [Entry::directory('projekty'), Entry::file('notatka.txt', 2048)])
            ->add('/home/projekty', [Entry::file('plan.md', 120)]);

        $this->app = new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
        $this->renderer = new RecordingRenderer();
    }

    private function loop(ScriptedTerminal $terminal): GameLoop
    {
        return new GameLoop(
            $terminal,
            new FrameComposer(
                $this->renderer,
                new FixedViewport(30, 80),
                new StubTranslator(),
                InputHandler::globalBindings(),
            ),
            $this->app->screens,
            $this->app->input,
            $this->app->state,
            framesPerSecond: self::TEST_FRAMES_PER_SECOND,
        );
    }

    public function testQuitKeyEndsTheLoopBeforeTheFirstFrame(): void
    {
        $this->loop(new ScriptedTerminal([KeyPress::special(Key::F10, '')]))->run();

        self::assertSame([], $this->renderer->frames);
    }

    public function testRendersUntilQuitKeyArrives(): void
    {
        $this->loop(new ScriptedTerminal([null, null, KeyPress::special(Key::F10, '')]))->run();

        self::assertCount(2, $this->renderer->frames);
    }

    public function testSignalEndsTheLoop(): void
    {
        $this->loop(new ScriptedTerminal([null, null], shutdownAfterReads: 2))->run();

        self::assertCount(1, $this->renderer->frames);
    }

    public function testDrawsCurrentDirectoryContents(): void
    {
        $this->loop(new ScriptedTerminal([null, KeyPress::special(Key::F10, '')]))->run();

        self::assertTrue($this->renderer->showsText('projekty/'));
        self::assertTrue($this->renderer->showsText('notatka.txt'));
        self::assertTrue($this->renderer->showsText('/home'), 'ścieżka stoi w nagłówku');
    }

    public function testFrameIsBuiltFromThreeLayersOnlyWhenAModalWindowIsOpen(): void
    {
        $this->loop(new ScriptedTerminal([null, KeyPress::special(Key::F10, '')]))->run();
        $plain = $this->renderer->last();

        self::assertNotNull($plain);
        self::assertCount(2, $plain->planes);

        $this->renderer->frames = [];

        // Dwa razy F10, bo okno nakładane połyka pierwszy klawisz — także ten,
        // który normalnie kończy aplikację. Okno otwiera `F12`: od kroku 20
        // `Enter` na pliku nie otwiera już niczego (P3).
        $this->loop(new ScriptedTerminal([
            KeyPress::special(Key::F12, ''),
            null,
            KeyPress::special(Key::F10, ''),
            KeyPress::special(Key::F10, ''),
        ]))->run();

        $withModal = $this->renderer->last();

        self::assertNotNull($withModal);
        self::assertCount(3, $withModal->planes);
        // Płaszczyzna nosi odtąd identyfikator okna, a nie stałe „modal”:
        // okien nakładanych jest od kroku 19 więcej niż jedno.
        self::assertSame('command', $withModal->planes[2]->id);
        self::assertTrue($withModal->planes[2]->opaque, 'okno zakrywa to, co pod nim');
        self::assertFalse($withModal->planes[1]->opaque, 'treść nie ma prawa wymazać oprawy');
    }

    /**
     * Pełna ścieżka kontekstu sesji: klawisz → skrót modułu → `FrameComposer`
     * podaje ekranowi kontekst → opis pliku ląduje w klatce.
     *
     * To jedyne miejsce, w którym `ReadsContext` sprawdza się **w komplecie**:
     * ekran modułu rysowany wprost dostałby kontekst z ręki testu, a tu dostaje
     * go tak, jak w aplikacji.
     */
    public function testModuleScreenReceivesSessionContextWhileTheFrameIsComposed(): void
    {
        $this->loop(new ScriptedTerminal([
            KeyPress::special(Key::ArrowDown, ''),
            KeyPress::ctrl('d'),
            null,
            KeyPress::special(Key::F10, ''),
        ]))->run();

        self::assertTrue($this->renderer->showsText('notatka.txt'), 'nazwa opisywanego pliku');
        self::assertTrue($this->renderer->showsText('PDF document, version 1.7'), 'opis z narzędzia');
        self::assertSame(['/home/notatka.txt'], $this->app->inspector->inspectedPaths);
    }

    public function testSelectionMovesBetweenFrames(): void
    {
        $this->loop(new ScriptedTerminal([
            null,
            KeyPress::special(Key::ArrowDown, ''),
            null,
            KeyPress::special(Key::F10, ''),
        ]))->run();

        self::assertCount(2, $this->renderer->frames);
        self::assertNotSame(
            $this->renderer->frames[0]->signature(),
            $this->renderer->frames[1]->signature(),
        );
    }

    public function testDrainsEveryKeyThatArrivedWithinOneTick(): void
    {
        $this->loop(new ScriptedTerminal([
            KeyPress::special(Key::ArrowDown, ''),
            KeyPress::special(Key::F12, ''),
            null,
        ], shutdownAfterReads: 4))->run();

        self::assertSame('notatka.txt', $this->app->state->context()->selection, 'pierwszy klawisz zadziałał');
        self::assertNotNull($this->app->state->overlays()->current(), 'oba klawisze z jednego taktu zadziałały');
    }

    /** @return array<string, array{Key, string}> */
    public static function screenKeys(): array
    {
        return [
            'F1 składa klatkę pomocy' => [Key::F1, 'layout.zone.help'],
            'F2 składa klatkę ustawień' => [Key::F2, 'layout.zone.settings'],
        ];
    }

    #[DataProvider('screenKeys')]
    public function testFunctionKeyChangesWhichScreenFillsTheMiddlePanel(Key $key, string $label): void
    {
        $this->loop(new ScriptedTerminal([
            KeyPress::special($key, ''),
            null,
            KeyPress::special(Key::F10, ''),
        ]))->run();

        self::assertTrue($this->renderer->showsText($label), 'etykieta strefy nazywa aktywny ekran');
    }

    /**
     * Reguła „strefa, której nie ma, oddaje wiersze środkowi” — sprawdzana od
     * kroku 21 na przeglądarce, bo była wtedy jedynym ekranem z pasem podglądu.
     *
     * Od D76 nie zamawiał go żaden ekran, a od kroku 47 (D78) **nie ma go
     * w kontrakcie w ogóle** — więc test zmienia zdanie po raz drugi i mówi to
     * samo z trzeciej strony: panel listy sięga w klatce 30×80 pełnych
     * dwudziestu trzech wierszy. Piętnaście zostawiał mu układ z pasem, osiem
     * brał pas — i te osiem widać tu policzone na narysowanej obwódce, a nie
     * w rachunku `HudLayout`.
     */
    public function testTheListTakesTheRowsOfTheAbsentPreviewStrip(): void
    {
        $this->loop(new ScriptedTerminal([null, KeyPress::special(Key::F10, '')]))->run();
        $browserRows = self::listRowsOf($this->renderer);

        $this->renderer->frames = [];
        $this->loop(new ScriptedTerminal([
            KeyPress::special(Key::F2, ''),
            null,
            KeyPress::special(Key::F10, ''),
        ]))->run();

        self::assertSame($browserRows, self::listRowsOf($this->renderer), 'oba ekrany są bez pasa');
        self::assertSame(
            (new HudLayout(30, 80, wideStatus: true))->list->rows,
            $browserRows,
            'panel rysuje się dokładnie na strefie, którą wyliczył układ',
        );
        self::assertSame(23, $browserRows, 'piętnaście wierszy listy plus osiem po pasie podglądu');
    }

    /** Wysokość panelu listy odczytana z jego obwódki w płaszczyźnie oprawy. */
    private static function listRowsOf(RecordingRenderer $renderer): int
    {
        $last = $renderer->last();
        $rows = 0;

        foreach ($last === null ? [] : $last->planes[0]->primitives as $primitive) {
            if ($primitive instanceof \LightManager\Application\Ui\Primitive\RoundRect) {
                $rows = max($rows, $primitive->bounds->rows);
            }
        }

        return $rows;
    }
}
