<?php

declare(strict_types=1);

namespace LightManager\Tests\Domain\ValueObject;

use LightManager\Domain\Exception\InvalidScrollPositionException;
use LightManager\Domain\ValueObject\ScrollPosition;
use PHPUnit\Framework\TestCase;

final class ScrollPositionTest extends TestCase
{
    public function testKeepsItsThreeNumbers(): void
    {
        $scroll = new ScrollPosition(6, 7, 30);

        self::assertSame(6, $scroll->first);
        self::assertSame(7, $scroll->visible);
        self::assertSame(30, $scroll->total);
    }

    public function testRejectsNegativeValues(): void
    {
        $this->expectException(InvalidScrollPositionException::class);

        new ScrollPosition(-1, 7, 30);
    }

    public function testRejectsWindowReachingPastTheList(): void
    {
        $this->expectException(InvalidScrollPositionException::class);
        $this->expectExceptionMessage('does not fit a list of');

        new ScrollPosition(25, 7, 30);
    }

    public function testScrollbarIsNeededOnlyWhenSomethingStaysOffScreen(): void
    {
        self::assertFalse((new ScrollPosition(0, 30, 30))->isNeeded());
        self::assertTrue((new ScrollPosition(0, 7, 30))->isNeeded());
        self::assertFalse((new ScrollPosition(0, 0, 0))->isNeeded());
    }

    public function testVisibleFractionSetsTheThumbHeight(): void
    {
        self::assertSame(0.25, (new ScrollPosition(0, 5, 20))->visibleFraction());
        self::assertSame(1.0, (new ScrollPosition(0, 0, 0))->visibleFraction());
    }

    /**
     * Postęp liczony jest względem wpisów niewidocznych, więc dojazd do końca
     * listy dosuwa suwak dokładnie do dołu szyny.
     */
    public function testProgressRunsFromTopToBottomOfTheRail(): void
    {
        self::assertSame(0.0, (new ScrollPosition(0, 5, 25))->progress());
        self::assertSame(0.5, (new ScrollPosition(10, 5, 25))->progress());
        self::assertSame(1.0, (new ScrollPosition(20, 5, 25))->progress());
        self::assertSame(0.0, (new ScrollPosition(0, 20, 20))->progress());
    }

    public function testComparesByValue(): void
    {
        $scroll = new ScrollPosition(6, 7, 30);

        self::assertTrue($scroll->equals(new ScrollPosition(6, 7, 30)));
        self::assertFalse($scroll->equals(new ScrollPosition(7, 7, 30)));
        self::assertFalse($scroll->equals(new ScrollPosition(6, 8, 30)));
        self::assertFalse($scroll->equals(new ScrollPosition(6, 7, 31)));
    }
}
