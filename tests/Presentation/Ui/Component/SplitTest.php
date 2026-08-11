<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\Split;
use LightManager\Presentation\Ui\SplitAxis;
use LightManager\Presentation\Ui\SplitState;
use PHPUnit\Framework\TestCase;

/**
 * Podział prostokąta na dwa i ognisko, które po nim chodzi.
 *
 * Komponent jest samą geometrią, więc i test jest o geometrii: dwa prostokąty
 * mają **pokryć prostokąt wyjściowy bez reszty i bez zakładki** — pojedyncza
 * kolumna zgubiona przy zaokrągleniu daje szparę w tle, którą widać dopiero
 * w terminalu.
 */
final class SplitTest extends TestCase
{
    public function testVerticalSplitCoversTheWholeRectangleWithoutGapOrOverlap(): void
    {
        [$left, $right] = Split::halves(new Rect(2, 3, 10, 81), SplitAxis::Vertical);

        self::assertSame([2, 3, 10, 41], [$left->row, $left->column, $left->rows, $left->columns]);
        self::assertSame([2, 44, 10, 40], [$right->row, $right->column, $right->rows, $right->columns]);
        self::assertSame($left->right() + 1, $right->column, 'panele stykają się co do kolumny');
        self::assertSame(81, $left->columns + $right->columns);
    }

    public function testHorizontalSplitDividesRowsInstead(): void
    {
        [$top, $bottom] = Split::halves(new Rect(0, 0, 15, 40), SplitAxis::Horizontal);

        self::assertSame([0, 8, 40], [$top->row, $top->rows, $top->columns]);
        self::assertSame([8, 7, 40], [$bottom->row, $bottom->rows, $bottom->columns]);
        self::assertSame(15, $top->rows + $bottom->rows);
    }

    public function testFractionMovesTheBorderAndIsClampedToTheRectangle(): void
    {
        [$left] = Split::halves(new Rect(0, 0, 10, 80), SplitAxis::Vertical, 0.25);
        [$whole, $nothing] = Split::halves(new Rect(0, 0, 10, 80), SplitAxis::Vertical, 4.0);

        self::assertSame(20, $left->columns);
        self::assertSame(80, $whole->columns, 'ułamek spoza zakresu jest przycinany');
        self::assertSame(0, $nothing->columns);
    }

    /**
     * Próg nie wynika z arytmetyki — dwa panele mieszczą się w 60 kolumnach bez
     * reszty i mimo to nie powstają. Ta sama reguła, którą `HudLayout` stosuje do
     * pasa podglądu.
     */
    public function testSplitDoesNotAppearBelowTheReadabilityThreshold(): void
    {
        self::assertTrue(Split::fits(new Rect(0, 0, 20, Split::MINIMUM_COLUMNS), SplitAxis::Vertical));
        self::assertFalse(Split::fits(new Rect(0, 0, 20, Split::MINIMUM_COLUMNS - 1), SplitAxis::Vertical));
        self::assertTrue(Split::fits(new Rect(0, 0, Split::MINIMUM_ROWS, 40), SplitAxis::Horizontal));
        self::assertFalse(Split::fits(new Rect(0, 0, Split::MINIMUM_ROWS - 1, 40), SplitAxis::Horizontal));
        self::assertFalse(Split::fits(new Rect(0, 0, 0, 200), SplitAxis::Vertical), 'pusty prostokąt nie dzieli się');
    }

    public function testBothChildrenDrawInTheirOwnHalf(): void
    {
        $primitives = (new Split(new Label('lewy'), new Label('prawy')))->draw(new Rect(4, 0, 3, 40));
        $runs = self::runsOf($primitives);

        self::assertCount(2, $runs);
        self::assertSame(['lewy', 0], [$runs[0]->text, $runs[0]->column]);
        self::assertSame(['prawy', 20], [$runs[1]->text, $runs[1]->column]);
    }

    public function testEmptyRectangleProducesNothing(): void
    {
        self::assertSame([], (new Split(new Label('a'), new Label('b')))->draw(new Rect(0, 0, 0, 40)));
    }

    /** Dziecko, które dostało za mało miejsca, nie rysuje nic — i to nie jest błąd. */
    public function testChildThatDidNotFitDrawsNothing(): void
    {
        $primitives = (new Split(new Label('lewy'), new Label('prawy'), SplitAxis::Vertical, 1.0))
            ->draw(new Rect(0, 0, 1, 40));

        self::assertCount(1, self::runsOf($primitives));
    }

    public function testFocusStartsOnTheFirstPaneAndMovesBackAndForth(): void
    {
        $state = new SplitState();

        self::assertFalse($state->focusesSecond());

        $state->moveFocus();
        self::assertTrue($state->focusesSecond());

        $state->moveFocus();
        self::assertFalse($state->focusesSecond());
    }

    /**
     * Reguła, dla której ta klasa w ogóle istnieje: podział wyłączony sprowadza
     * ognisko na pierwszy panel, bo drugiego nie ma na ekranie, a klawisze muszą
     * dokądś trafiać.
     */
    public function testTurningTheSplitOffBringsFocusBackToTheFirstPane(): void
    {
        $state = new SplitState();
        $state->moveFocus();

        $state->useSplit(true);
        self::assertTrue($state->focusesSecond(), 'włączony podział ogniska nie rusza');

        $state->useSplit(false);
        self::assertFalse($state->focusesSecond());
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<TextRun>
     */
    private static function runsOf(array $primitives): array
    {
        return array_values(array_filter(
            $primitives,
            static fn (Primitive $primitive): bool => $primitive instanceof TextRun && $primitive->role === Role::Text,
        ));
    }
}
