<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui;

use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\SplitAxis;
use LightManager\Presentation\Ui\SplitState;
use PHPUnit\Framework\TestCase;

/**
 * Granica podziału: chwytanie, przeciąganie, granice i zapis (krok 55).
 *
 * Do tego kroku klasa trzymała samą stronę ogniska i test miał w niej dwa
 * zdania. Proporcja jest jedyną rzeczą, jaką krok 55 dokłada do stanu poza
 * wejściem — i to jej dotyczy wszystko poniżej.
 */
final class SplitStateTest extends TestCase
{
    /** Sto kolumn i dwadzieścia wierszy: granica pionowa wypada w kolumnie 50. */
    private static function bounds(): Rect
    {
        return new Rect(0, 0, 20, 100);
    }

    public function testStartsInTheMiddleAndOnTheFirstPane(): void
    {
        $state = new SplitState();

        self::assertSame(SplitState::DEFAULT_FRACTION, $state->fraction());
        self::assertFalse($state->focusesSecond());
    }

    /** Proporcja podana z zewnątrz jest ścinana do granic, a nie odrzucana. */
    public function testClampsTheFractionGivenFromOutside(): void
    {
        self::assertSame(SplitState::MINIMUM_FRACTION, (new SplitState(0.0))->fraction());
        self::assertSame(SplitState::MAXIMUM_FRACTION, (new SplitState(1.0))->fraction());
    }

    /** Chwyta **granica i komórka przed nią** — dwie stykające się obwódki. */
    public function testGrabsOnBothCellsOfTheBoundary(): void
    {
        foreach ([49, 50] as $column) {
            $state = new SplitState();

            self::assertTrue($state->pointer(PointerEvent::press(5, $column), self::bounds(), SplitAxis::Vertical));
            self::assertTrue($state->isDragging());
        }
    }

    public function testDoesNotGrabAwayFromTheBoundary(): void
    {
        $state = new SplitState();

        self::assertFalse($state->pointer(PointerEvent::press(5, 20), self::bounds(), SplitAxis::Vertical));
        self::assertFalse($state->isDragging());
    }

    /** Ruch bez uprzedniego chwycenia granicy do niej nie należy. */
    public function testDragWithoutAGrabIsNotItsBusiness(): void
    {
        $state = new SplitState();

        self::assertFalse($state->pointer(PointerEvent::drag(5, 30), self::bounds(), SplitAxis::Vertical));
    }

    public function testDraggingMovesTheBoundaryAndStopsAtTheLimits(): void
    {
        $state = new SplitState();
        $state->pointer(PointerEvent::press(5, 50), self::bounds(), SplitAxis::Vertical);

        $state->pointer(PointerEvent::drag(5, 30), self::bounds(), SplitAxis::Vertical);
        self::assertSame(0.3, $state->fraction());

        $state->pointer(PointerEvent::drag(5, 2), self::bounds(), SplitAxis::Vertical);
        self::assertSame(SplitState::MINIMUM_FRACTION, $state->fraction());

        $state->pointer(PointerEvent::drag(5, 98), self::bounds(), SplitAxis::Vertical);
        self::assertSame(SplitState::MAXIMUM_FRACTION, $state->fraction());
    }

    /** Oś pozioma liczy się wierszami — ten sam rachunek, inna współrzędna. */
    public function testTheHorizontalAxisCountsRows(): void
    {
        $state = new SplitState();

        self::assertTrue($state->pointer(PointerEvent::press(10, 5), self::bounds(), SplitAxis::Horizontal));

        $state->pointer(PointerEvent::drag(6, 5), self::bounds(), SplitAxis::Horizontal);
        self::assertSame(0.3, $state->fraction());
    }

    /** Zapis pada **po zwolnieniu przycisku**, raz, i w procentach. */
    public function testPersistsOnlyWhenTheButtonComesUp(): void
    {
        $saved = [];
        $state = new SplitState(0.5, static function (int $percent) use (&$saved): void {
            $saved[] = $percent;
        });

        $state->pointer(PointerEvent::press(5, 50), self::bounds(), SplitAxis::Vertical);
        $state->pointer(PointerEvent::drag(5, 35), self::bounds(), SplitAxis::Vertical);

        self::assertSame([], $saved, 'w trakcie przeciągania nie zapisujemy nic');

        $state->pointer(PointerEvent::release(5, 35), self::bounds(), SplitAxis::Vertical);

        self::assertSame([35], $saved);
    }

    /** Zwolnienie bez przeciągania nie jest sprawą granicy i niczego nie zapisuje. */
    public function testReleaseWithoutADragIsIgnored(): void
    {
        $saved = [];
        $state = new SplitState(0.5, static function (int $percent) use (&$saved): void {
            $saved[] = $percent;
        });

        self::assertFalse($state->pointer(PointerEvent::release(5, 50), self::bounds(), SplitAxis::Vertical));
        self::assertSame([], $saved);
    }

    /**
     * Proporcja podana z ustawień jest **pomijana w trakcie przeciągania**:
     * czytana co klatkę cofałaby granicę pod ręką użytkownika.
     */
    public function testTheSettingIsIgnoredWhileDragging(): void
    {
        $state = new SplitState();
        $state->pointer(PointerEvent::press(5, 50), self::bounds(), SplitAxis::Vertical);
        $state->pointer(PointerEvent::drag(5, 30), self::bounds(), SplitAxis::Vertical);

        $state->useFraction(0.5);

        self::assertSame(0.3, $state->fraction());

        $state->pointer(PointerEvent::release(5, 30), self::bounds(), SplitAxis::Vertical);
        $state->useFraction(0.5);

        self::assertSame(0.5, $state->fraction());
    }

    /** Wyłączony podział sprowadza ognisko na pierwszy panel i kończy przeciąganie. */
    public function testDisablingTheSplitBringsTheFocusBackAndDropsTheDrag(): void
    {
        $state = new SplitState();
        $state->moveFocus();
        $state->pointer(PointerEvent::press(5, 50), self::bounds(), SplitAxis::Vertical);

        $state->useSplit(false);

        self::assertFalse($state->focusesSecond());
        self::assertFalse($state->isDragging());
    }

    public function testFocusCanBePutDirectly(): void
    {
        $state = new SplitState();

        $state->focus(true);
        self::assertTrue($state->focusesSecond());

        $state->focus(false);
        self::assertFalse($state->focusesSecond());
    }

    /** Pusty prostokąt nie ma granicy, więc nie ma czego chwytać. */
    public function testAnEmptyRectangleHasNoBoundary(): void
    {
        $state = new SplitState();

        self::assertFalse(
            $state->pointer(PointerEvent::press(0, 0), new Rect(0, 0, 0, 0), SplitAxis::Vertical),
        );
    }
}
