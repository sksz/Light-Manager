<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Container;

use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\Component\Spacer;
use LightManager\Presentation\Ui\Container\Slot;
use LightManager\Presentation\Ui\Container\VStack;
use PHPUnit\Framework\TestCase;

final class VStackTest extends TestCase
{
    public function testFixedSlotsGetWhatTheyAskedForAndFlexibleTakesTheRest(): void
    {
        $stack = new VStack([
            Slot::fixed(new Spacer(), 3),
            Slot::flexible(new Spacer()),
            Slot::fixed(new Spacer(), 2),
        ]);

        self::assertSame([3, 15, 2], $stack->distribute(20));
    }

    public function testSpareRowsSplitEvenlyBetweenFlexibleSlots(): void
    {
        $stack = new VStack([
            Slot::flexible(new Spacer()),
            Slot::flexible(new Spacer()),
        ]);

        self::assertSame([5, 5], $stack->distribute(10));
        self::assertSame([6, 5], $stack->distribute(11), 'reszta z dzielenia idzie do pierwszej szczeliny');
    }

    public function testNothingIsStretchedWhenNoSlotAskedForIt(): void
    {
        $stack = new VStack([Slot::fixed(new Spacer(), 2), Slot::fixed(new Spacer(), 3)]);

        self::assertSame([2, 3], $stack->distribute(20));
    }

    public function testSlotsYieldInDeclaredOrderWhenRowsRunShort(): void
    {
        $stack = new VStack([
            Slot::fixed(new Spacer(), 3, yieldOrder: 2),
            Slot::flexible(new Spacer()),
            new Slot(new Spacer(), 8, 8, yieldOrder: 0),
            Slot::fixed(new Spacer(), 3, yieldOrder: 1),
        ]);

        // Pas o kolejności 0 nie utrzymałby swojego minimum, więc znika
        // w całości, a zwolnione wiersze wracają do szczeliny elastycznej.
        self::assertSame([3, 2, 0, 3], $stack->distribute(8));
        self::assertSame([3, 1, 0, 0], $stack->distribute(4));
        self::assertSame([1, 1, 0, 0], $stack->distribute(2));
    }

    public function testFlexibleMinimumSurvivesUntilEverythingElseIsGone(): void
    {
        $stack = new VStack([
            Slot::fixed(new Spacer(), 5, yieldOrder: 0),
            Slot::flexible(new Spacer()),
        ]);

        self::assertSame([0, 1], $stack->distribute(1));
    }

    public function testZeroRowsLeaveNothingForAnybody(): void
    {
        $stack = new VStack([Slot::fixed(new Spacer(), 3), Slot::flexible(new Spacer())]);

        self::assertSame([0, 0], $stack->distribute(0));
    }

    public function testChildrenAreDrawnInTheirOwnRectangles(): void
    {
        $probe = new RecordingComponent();
        $stack = new VStack([
            Slot::fixed($probe, 2),
            Slot::fixed($probe, 3),
        ]);

        $stack->draw(new Rect(4, 1, 5, 20));

        self::assertSame([[4, 1, 2, 20], [6, 1, 3, 20]], $probe->bounds);
    }

    public function testSlotWithoutRowsIsNotDrawnAtAll(): void
    {
        $probe = new RecordingComponent();
        $stack = new VStack([Slot::fixed($probe, 0), Slot::fixed($probe, 2)]);

        $stack->draw(new Rect(0, 0, 2, 10));

        self::assertCount(1, $probe->bounds);
    }
}
