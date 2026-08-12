<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli;

use LightManager\Application\Ui\Frame;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Cli\FrameComposer;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\RecordingRenderer;
use LightManager\Tests\Support\ResizableViewport;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Gwarancja, na której stoi krok 33: składanie klatki pyta o rozmiar okna
 * **przy każdej klatce**, więc zmiana rozmiaru nie wymaga od niego niczego —
 * następna klatka po prostu powstaje w nowym rozmiarze.
 *
 * Do kroku 33 ta własność była prawdziwa przypadkiem (odpowiedź portu i tak
 * była stała); od kroku 33 jest umową, bo `TerminalSizeService` zaczął
 * odpowiadać różnie. Ten test pilnuje, żeby nikt nie „zoptymalizował” pytania
 * o rozmiar do jednorazowego.
 */
final class FrameComposerResizeTest extends TestCase
{
    private ScreenFixture $app;

    private RecordingRenderer $renderer;

    private ResizableViewport $viewport;

    private FrameComposer $composer;

    protected function setUp(): void
    {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/home', [Entry::directory('dokumenty'), Entry::file('notatka.txt', 12)])
            ->add('/home/dokumenty', []);

        $this->app = new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
        $this->renderer = new RecordingRenderer();
        $this->viewport = new ResizableViewport(30, 80);
        $this->composer = new FrameComposer($this->renderer, $this->viewport, new StubTranslator());
    }

    public function testNextFrameGrowsTogetherWithTheWindow(): void
    {
        $this->composer->render($this->app->screens->current(), $this->app->state);
        $this->viewport->resize(40, 120);
        $this->composer->render($this->app->screens->current(), $this->app->state);

        self::assertSame([30, 80], self::frameSize($this->renderer->frames[0]));
        self::assertSame([40, 120], self::frameSize($this->renderer->frames[1]));
    }

    public function testNextFrameShrinksTogetherWithTheWindow(): void
    {
        $this->composer->render($this->app->screens->current(), $this->app->state);
        $this->viewport->resize(12, 48);
        $this->composer->render($this->app->screens->current(), $this->app->state);

        self::assertSame([12, 48], self::frameSize($this->renderer->frames[1]));
    }

    /**
     * Poniżej progu formatowania klatka liczy się tak, jakby kolumn było
     * dwadzieścia — rysuje się, co się zmieści, bez planszy zastępczej
     * (krok 33, rozstrzygnięcie nr 4).
     */
    public function testTinyWindowStillProducesAFrame(): void
    {
        $this->viewport->resize(5, 10);
        $this->composer->render($this->app->screens->current(), $this->app->state);

        [$rows, $columns] = self::frameSize($this->renderer->frames[0]);

        self::assertSame(5, $rows);
        self::assertSame(20, $columns);
        self::assertNotSame([], $this->renderer->primitives());
    }

    /**
     * Rozmiar klatki odczytany z prostokąta płaszczyzny spodniej — tego samego,
     * którym `FrameComposer` opisuje całość okna.
     *
     * @return array{int, int} wiersze i kolumny
     */
    private static function frameSize(Frame $frame): array
    {
        $bounds = $frame->planes[0]->bounds;

        return [$bounds->rows, $bounds->columns];
    }
}
