<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Rendering;

use LightManager\Application\Ui\Frame;
use LightManager\Application\Ui\Plane;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextMark;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Infrastructure\Rendering\AnsiPalette;
use LightManager\Infrastructure\Rendering\TextFrameRenderer;
use LightManager\Infrastructure\Rendering\Theme;
use LightManager\Infrastructure\Rendering\ThemeService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Co zostało rendererowi tekstowemu po kroku 56: **paleta i bajty**.
 *
 * Degradacja kształtów w siatce znakowej wyszła stąd do
 * `Application\Ui\FrameText` wraz z rachunkiem (i wraz z testem, który jej
 * pilnuje) — tu zostaje pytanie, na które odpowiada wyłącznie ten tor: czy rola
 * zamienia się na właściwy kod ANSI i czy kod pada tylko tam, gdzie rola się
 * zmienia.
 */
final class TextFrameRendererTest extends TestCase
{
    use ResetsSingletons;

    protected function tearDown(): void
    {
        $this->resetSingleton(ThemeService::class);
    }

    /**
     * Dopasowanie filtra: para kodów na początku fragmentu i powrót do koloru
     * napisu tuż za nim — czyli atrybut kończy się dokładnie tam, gdzie kończy
     * się fragment.
     */
    public function testAHighlightBecomesAPairOfCodesAndEndsWithItsFragment(): void
    {
        $palette = new AnsiPalette(true);
        $theme = Theme::grafit();
        $line = self::ansi(
            $palette,
            $theme,
            new TextRun(0, 0, 'plik.txt', Role::Text),
            new TextMark(0, 0, 'pli', Role::Background, Role::Accent),
        );

        self::assertStringContainsString(
            $palette->background($theme->accent) . $palette->foreground($theme->background) . 'pli',
            $line,
        );
        self::assertStringContainsString($palette->foreground($theme->text) . 'k.txt', $line);
    }

    /**
     * **Trzynasta rola ma swój kolor w każdej z czterech palet** i jest to
     * sprawdzenie z lekcji kroku 43 (D80): rola dobrana znaczeniowo bez
     * sprawdzenia palety bywa rolą bez koloru.
     *
     * Prostokąt zaznaczenia musi się odróżniać naraz od **dwóch** rzeczy — od
     * tła wiersza pod kursorem i od tła paneli — bo z pierwszym myli się co do
     * znaczenia, a w drugim znika.
     */
    public function testTheMarqueeHasItsOwnColourInEveryPalette(): void
    {
        foreach ([Theme::grafit(), Theme::nordyk(), Theme::papier(), Theme::indygo()] as $theme) {
            self::assertNotSame($theme->selection, $theme->marquee, 'zaznaczenie ≠ kursor listy');
            self::assertNotSame($theme->surface, $theme->marquee, 'zaznaczenie ≠ tło panelu');
            self::assertNotSame($theme->background, $theme->marquee, 'zaznaczenie ≠ tło klatki');
            self::assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $theme->marquee);
        }
    }

    /** Zaznaczenie w siatce znakowej: tło trzynastej roli, pismo — roli zaznaczonego wiersza. */
    public function testTheMarqueePaintsTheCellsItCovers(): void
    {
        $palette = new AnsiPalette(true);
        $theme = Theme::grafit();
        $line = self::ansi(
            $palette,
            $theme,
            new TextRun(0, 0, 'plik.txt', Role::Text),
            new TextMark(0, 2, 'ik.t', Role::SelectionText, Role::Marquee),
        );

        self::assertStringContainsString(
            $palette->background($theme->marquee) . $palette->foreground($theme->selectionText) . 'ik.t',
            $line,
        );
    }

    /** Prymityw poza siatką nie wywraca bufora — jak każdy inny. */
    public function testAPrimitiveOutsideTheGridIsIgnored(): void
    {
        $line = self::ansi(
            new AnsiPalette(true),
            Theme::grafit(),
            new TextMark(9, 9, 'pli', Role::Background, Role::Accent),
        );

        self::assertStringNotContainsString('pli', $line);
    }

    private static function ansi(AnsiPalette $palette, Theme $theme, Primitive ...$primitives): string
    {
        $frame = new Frame([new Plane('content', new Rect(0, 0, 1, 10), array_values($primitives))]);
        $renderer = new TextFrameRenderer($palette);

        return $renderer->encode($renderer->composeBuffer($frame, $theme, 1, 10));
    }
}
