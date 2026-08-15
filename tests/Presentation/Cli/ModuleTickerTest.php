<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli;

use LightManager\Application\Dto\Settings;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Cli\ModuleTicker;
use LightManager\Presentation\Cli\ProblemPresenter;
use LightManager\Tests\Support\FakeModule;
use LightManager\Tests\Support\StubTranslator;
use LightManager\Tests\Support\TickingModule;
use PHPUnit\Framework\TestCase;

/**
 * Takt modułów — mechanizm rdzenia z kroku 45.
 *
 * Trzy reguły taktu, z których dwie da się sprawdzić wprost i obie są tutaj:
 * dostaje go **każdy** przyjęty moduł, który o niego poprosił, i **wyjątek
 * modułu nie przerywa pętli**. Trzeciej — „takt jest tani” — testem się nie
 * sprawdza; od niej jest oś `--loop` w `bin/render-bench`.
 */
final class ModuleTickerTest extends TestCase
{
    /** Moduł bez zdolności jest pomijany — i to **przy składaniu**, nie co klatkę. */
    public function testOnlyModulesThatAskedForItGetATick(): void
    {
        $ticking = new TickingModule('gra');
        $ticker = ModuleTicker::of([new FakeModule('cicha'), $ticking], $this->presenter());
        $state = new LoopState(new Settings());

        $ticker->tick($state, 1.0);
        $ticker->tick($state, 2.0);

        self::assertSame([1.0, 2.0], $ticking->ticks, 'czas przychodzi z zewnątrz, nie z zegara modułu');
    }

    /** Bez ani jednego chętnego takt nie ma czego robić — i mówi to wprost. */
    public function testWithoutAnyTickingModuleTheTickerIsEmpty(): void
    {
        self::assertTrue(ModuleTicker::of([new FakeModule('cicha')], $this->presenter())->isEmpty());
        self::assertFalse(ModuleTicker::of([new TickingModule('gra')], $this->presenter())->isEmpty());
    }

    /**
     * **Wyjątek modułu nie przerywa pętli** — staje w pasku stanu jako zdanie, tą
     * samą drogą, którą łapane są wyjątki ekranu.
     */
    public function testAModuleThrowingDoesNotBreakTheLoop(): void
    {
        $state = new LoopState(new Settings());
        $after = new TickingModule('druga');
        $ticker = ModuleTicker::of([new TickingModule('psuta', fails: true), $after], $this->presenter());

        $ticker->tick($state, 5.0);

        self::assertSame(MessageTone::Error, $state->message()?->tone);
        self::assertSame([5.0], $after->ticks, 'wyjątek jednego nie zabiera taktu pozostałym');
    }

    private function presenter(): ProblemPresenter
    {
        return new ProblemPresenter(new StubTranslator());
    }
}
