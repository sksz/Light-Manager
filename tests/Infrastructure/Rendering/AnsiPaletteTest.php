<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Rendering;

use LightManager\Infrastructure\Rendering\AnsiPalette;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AnsiPaletteTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function colors256(): array
    {
        return [
            'czerń trafia w róg kostki' => ['#000000', "\e[38;5;16m"],
            'biel trafia w przeciwległy róg' => ['#ffffff', "\e[38;5;231m"],
            'tło motywu wpada w rampę szarości' => ['#16181c', "\e[38;5;234m"],
            'akcent zostaje w kostce kolorów' => ['#d9a441', "\e[38;5;179m"],
        ];
    }

    #[DataProvider('colors256')]
    public function testMapsToTheNearestPaletteEntry(string $hex, string $expected): void
    {
        self::assertSame($expected, (new AnsiPalette(true))->foreground($hex));
    }

    public function testBackgroundUsesItsOwnPrefix(): void
    {
        self::assertSame("\e[48;5;16m", (new AnsiPalette(true))->background('#000000'));
    }

    /** @return array<string, array{string, string}> */
    public static function basicColors(): array
    {
        return [
            'jasny tekst to rozjaśniona biel' => ['#dcdfe4', "\e[97m"],
            'ciemne tło to czerń' => ['#16181c', "\e[30m"],
            'obwódka to rozjaśniona czerń' => ['#3b414c', "\e[90m"],
            'drugorzędny to zwykła biel' => ['#8d939d', "\e[37m"],
            'akcent ląduje w żółtym' => ['#d9a441', "\e[93m"],
            'błąd ląduje w czerwonym' => ['#e0645c', "\e[91m"],
        ];
    }

    /** Terminal bez palety 256 dostaje najbliższy z szesnastu kolorów podstawowych. */
    #[DataProvider('basicColors')]
    public function testDegradesToBasicColors(string $hex, string $expected): void
    {
        self::assertSame($expected, (new AnsiPalette(false))->foreground($hex));
    }

    /**
     * Odległość euklidesowa w RGB stawia tę czerwień bliżej średniej szarości
     * niż czerwieni — komunikat o błędzie wyszedłby wtedy szary. Odcień musi
     * rozstrzygać pierwszy.
     */
    public function testDesaturatedRedStaysRedInsteadOfTurningGrey(): void
    {
        self::assertSame("\e[91m", (new AnsiPalette(false))->foreground('#e0645c'));
    }

    /** Tło zaznaczenia ma ledwie ślad błękitu — ma zostać szarością, nie granatem. */
    public function testNearNeutralColourFallsIntoTheGreyRamp(): void
    {
        self::assertSame("\e[100m", (new AnsiPalette(false))->background('#313845'));
    }

    public function testBasicBackgroundUsesTheOtherRange(): void
    {
        self::assertSame("\e[40m", (new AnsiPalette(false))->background('#16181c'));
        self::assertSame("\e[103m", (new AnsiPalette(false))->background('#d9a441'));
        self::assertSame("\e[107m", (new AnsiPalette(false))->background('#f2f4f7'));
    }

    public function testReadsThePaletteFromTheEnvironment(): void
    {
        $term = getenv('TERM');
        $colorTerm = getenv('COLORTERM');

        try {
            putenv('TERM=xterm-256color');
            putenv('COLORTERM');
            self::assertSame("\e[38;5;16m", AnsiPalette::fromEnvironment()->foreground('#000000'));

            putenv('TERM=xterm');
            self::assertSame("\e[30m", AnsiPalette::fromEnvironment()->foreground('#000000'));

            putenv('COLORTERM=truecolor');
            self::assertSame("\e[38;5;16m", AnsiPalette::fromEnvironment()->foreground('#000000'));
        } finally {
            putenv($term === false ? 'TERM' : 'TERM=' . $term);
            putenv($colorTerm === false ? 'COLORTERM' : 'COLORTERM=' . $colorTerm);
        }
    }

    public function testAcceptsHexWithoutHash(): void
    {
        self::assertSame(
            (new AnsiPalette(true))->foreground('#d9a441'),
            (new AnsiPalette(true))->foreground('d9a441'),
        );
    }
}
