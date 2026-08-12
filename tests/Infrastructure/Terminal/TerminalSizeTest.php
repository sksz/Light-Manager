<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Terminal;

use LightManager\Infrastructure\Terminal\TerminalSize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TerminalSizeTest extends TestCase
{
    public function testReservesExactlyOneRowOfWindowHeight(): void
    {
        // Okno 90×26 komórek po 13 px wysokości — zmierzone w XTermie.
        $size = new TerminalSize(540, 338, 90, 26);

        self::assertSame(325, $size->heightPixelsWithoutBottomRow());
    }

    public function testKeepsFullWidthUntouched(): void
    {
        $size = new TerminalSize(540, 338, 90, 26);

        self::assertSame(540, $size->widthPixels);
    }

    /**
     * Reszta z dzielenia zostaje przy rezerwie, a nie przy klatce — inaczej
     * płótno mogłoby wyjść wyższe niż wiersze, które terminal ma do dyspozycji.
     */
    public function testHeightNotDivisibleByRowsStaysBelowWindow(): void
    {
        $size = new TerminalSize(540, 341, 90, 26);

        // 341 - intdiv(341, 26) = 341 - 13 = 328, czyli mniej niż 341.
        self::assertSame(328, $size->heightPixelsWithoutBottomRow());
    }

    /** Po `equals()` renderer poznaje zmianę okna — czułość na każde z czterech pól jest treścią umowy. */
    public function testEqualsComparesBothUnitsAtOnce(): void
    {
        $size = new TerminalSize(540, 338, 90, 26);

        self::assertTrue($size->equals(new TerminalSize(540, 338, 90, 26)));
        self::assertFalse($size->equals(new TerminalSize(541, 338, 90, 26)));
        self::assertFalse($size->equals(new TerminalSize(540, 339, 90, 26)));
        self::assertFalse($size->equals(new TerminalSize(540, 338, 91, 26)));
        self::assertFalse($size->equals(new TerminalSize(540, 338, 90, 27)));
    }

    /** @return array<string, array{int, int}> */
    public static function degenerateSizes(): array
    {
        return [
            'jeden wiersz' => [13, 1],
            'zero wierszy' => [13, 0],
            'zerowa wysokość' => [0, 26],
            'wysokość mniejsza od wiersza' => [1, 26],
        ];
    }

    /**
     * Rozmiar zdegenerowany nie ma dawać zera ani liczby ujemnej — Imagick
     * odmówiłby utworzenia takiego płótna, a to wywróciłoby całą klatkę.
     */
    #[DataProvider('degenerateSizes')]
    public function testNeverReturnsUnusableHeight(int $heightPixels, int $rows): void
    {
        $size = new TerminalSize(540, $heightPixels, 90, $rows);

        self::assertGreaterThanOrEqual(1, $size->heightPixelsWithoutBottomRow());
    }
}
