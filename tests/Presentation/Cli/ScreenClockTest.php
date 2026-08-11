<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli;

use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Cli\FrameComposer;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Component\ProgressBar;
use LightManager\Presentation\Ui\NeedsTime;
use LightManager\Presentation\Ui\ScreenInterface;
use LightManager\Presentation\Ui\ScreenOutcome;
use LightManager\Presentation\Ui\ScreenZone;
use LightManager\Tests\Support\FixedViewport;
use LightManager\Tests\Support\RecordingRenderer;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Droga zegara od pętli do ekranu (krok 23).
 *
 * Do tego kroku czas dostawały wyłącznie okna nakładane, bo tylko tam było coś,
 * co zmienia się samo z siebie — karetka w polu tekstowym. Pasek postępu
 * w trybie „nie wiadomo ile jeszcze” jest drugą taką rzeczą i stoi **na
 * ekranie**, nie nad nim.
 *
 * Test sprawdza całą drogę, a nie samo wywołanie: podaje pętli dwie różne
 * chwile i patrzy, czy wypełnienie w klatce naprawdę stoi w dwóch różnych
 * miejscach. Sprawdzenie samego `useTime()` przeszłoby także wtedy, gdyby
 * składanie klatki wołało je **po** narysowaniu ekranu — czyli za późno.
 */
final class ScreenClockTest extends TestCase
{
    public function testScreenDeclaringNeedsTimeReceivesTheClockOfTheCurrentFrame(): void
    {
        $screen = new MovingScreen();
        $state = new LoopState();
        $renderer = new RecordingRenderer();
        $composer = new FrameComposer($renderer, new FixedViewport(24, 80), new StubTranslator());

        $state->tick(0.0);
        $composer->render($screen, $state);
        $atStart = self::fillColumn($renderer);

        $state->tick(1.2);
        $composer->render($screen, $state);
        $atTurn = self::fillColumn($renderer);

        self::assertSame([0.0, 1.2], $screen->seen, 'ekran dostaje chwilę każdej klatki');
        self::assertNotSame($atStart, $atTurn, 'i ta chwila naprawdę dochodzi do rysunku');
        self::assertGreaterThan($atStart, $atTurn);
    }

    /** Ekran, który czasu nie deklaruje, rysuje się tak samo jak przedtem. */
    public function testScreenWithoutNeedsTimeIsNotAskedForAnything(): void
    {
        $screen = new StillScreen();
        $state = new LoopState();
        $renderer = new RecordingRenderer();
        $composer = new FrameComposer($renderer, new FixedViewport(24, 80), new StubTranslator());

        $state->tick(9.9);
        $composer->render($screen, $state);

        self::assertNotNull($renderer->last());
    }

    private static function fillColumn(RecordingRenderer $renderer): int
    {
        foreach ($renderer->primitives() as $primitive) {
            if ($primitive instanceof Bar && $primitive->role === Role::Accent) {
                return $primitive->bounds->column;
            }
        }

        self::fail('klatka nie zawiera wypełnienia paska postępu');
    }
}

/** Ekran z paskiem postępu, który nie zna postępu — jedyny ruchomy element klatki. */
final class MovingScreen implements ScreenInterface, NeedsTime
{
    /** @var list<float> */
    public array $seen = [];

    private float $now = 0.0;

    public function useTime(float $now): void
    {
        $this->seen[] = $now;
        $this->now = $now;
    }

    public function id(): string
    {
        return 'test.moving';
    }

    public function labelKey(): string
    {
        return 'layout.zone.help';
    }

    public function header(): ?ScreenZone
    {
        return null;
    }

    public function preview(): ?ScreenZone
    {
        return null;
    }

    public function bindings(): array
    {
        return [];
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        return ScreenOutcome::stay();
    }

    public function draw(Rect $bounds): array
    {
        return (new ProgressBar(null, 'praca', $this->now))->draw($bounds->line(0));
    }
}

/** Ten sam ekran bez deklaracji czasu — dowód, że kontrakt nie urósł dla wszystkich. */
final class StillScreen implements ScreenInterface
{
    public function id(): string
    {
        return 'test.still';
    }

    public function labelKey(): string
    {
        return 'layout.zone.help';
    }

    public function header(): ?ScreenZone
    {
        return null;
    }

    public function preview(): ?ScreenZone
    {
        return null;
    }

    public function bindings(): array
    {
        return [];
    }

    public function handle(KeyPress $key): ScreenOutcome
    {
        return ScreenOutcome::stay();
    }

    public function draw(Rect $bounds): array
    {
        return (new ProgressBar(0.5, 'praca'))->draw($bounds->line(0));
    }
}
