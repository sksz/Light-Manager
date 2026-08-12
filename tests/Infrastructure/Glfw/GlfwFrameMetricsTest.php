<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Glfw;

use LightManager\Application\Ui\Corner;
use LightManager\Application\Ui\Rect;
use LightManager\Infrastructure\Glfw\GlfwFrameMetrics;
use PHPUnit\Framework\TestCase;

/**
 * Metryki są czystą arytmetyką, więc testują się bez okna i bez kontekstu —
 * tak samo jak `SixelFrameMetrics`, którego są lustrem (krok 35).
 */
final class GlfwFrameMetricsTest extends TestCase
{
    public function testDividesCanvasIntoGrid(): void
    {
        $metrics = new GlfwFrameMetrics(1000, 600, 30, 100);

        self::assertSame(20, $metrics->rowHeight);
        self::assertSame(10, $metrics->columnWidth);
        self::assertSame(0, $metrics->topOf(0));
        self::assertSame(60, $metrics->topOf(3));
        self::assertSame(70, $metrics->xOf(7));
        self::assertSame(10, $metrics->middleOf(0));
    }

    /** Rozmiar pisma jest udziałem wysokości wiersza, nie odwrotnie — jak w torze sixelowym. */
    public function testFontSizeFollowsRowHeight(): void
    {
        $metrics = new GlfwFrameMetrics(1000, 600, 30, 100);

        self::assertSame(round(20 * GlfwFrameMetrics::FONT_SIZE_RATIO, 1), $metrics->fontSize);
        self::assertSame(round(20 * GlfwFrameMetrics::FONT_SIZE_RATIO), $metrics->baselineOf(0));
    }

    /**
     * Prawa krawędź liczy się **od prawej strony framebuffera**, więc kształt
     * pełnej szerokości ma jednakowe marginesy także wtedy, gdy szerokość nie
     * dzieli się przez liczbę kolumn.
     */
    public function testRightEdgeIsMirroredAgainstTheLeftOne(): void
    {
        // 1000 px na 166 kolumn to komórka 6-pikselowa i cztery piksele reszty.
        $metrics = new GlfwFrameMetrics(1000, 600, 46, 166);
        $full = new Rect(0, 0, 46, 166);

        $leftMargin = $metrics->xOf($full->column);
        $rightMargin = $metrics->widthPixels - 1 - $metrics->rightOf($full);

        self::assertSame($leftMargin, $rightMargin);
        self::assertSame(999, $metrics->rightOf($full));
    }

    /** Promień nie przekracza połowy boku — łuki dwóch rogów nie mają prawa się spotkać. */
    public function testRadiusNeverExceedsHalfTheShorterSide(): void
    {
        $metrics = new GlfwFrameMetrics(1000, 600, 30, 100);

        self::assertLessThanOrEqual(5, $metrics->radiusFor(Corner::Round, 10, 200));
        self::assertLessThanOrEqual(4, $metrics->radiusFor(Corner::Round, 200, 8));
        self::assertGreaterThanOrEqual(1, $metrics->radiusFor(Corner::Soft, 2, 2));
    }

    /** Zdegenerowana siatka nie ma prawa dać dzielenia przez zero ani zerowej komórki. */
    public function testDegenerateGridStillYieldsUsableCell(): void
    {
        $metrics = new GlfwFrameMetrics(0, 0, 0, 0);

        self::assertSame(1, $metrics->rowHeight);
        self::assertSame(1, $metrics->columnWidth);
        self::assertGreaterThanOrEqual(6.0, $metrics->fontSize);
    }
}
