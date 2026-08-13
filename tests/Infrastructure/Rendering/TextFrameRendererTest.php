<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Rendering;

use LightManager\Application\Ui\Primitive\TextMark;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Role;
use LightManager\Infrastructure\Rendering\AnsiPalette;
use LightManager\Infrastructure\Rendering\CellBuffer;
use LightManager\Infrastructure\Rendering\TextFrameRenderer;
use LightManager\Infrastructure\Rendering\Theme;
use LightManager\Infrastructure\Rendering\ThemeService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Degradacja kształtów w siatce znakowej — krok 30.
 *
 * Renderer tekstowy pisze na terminal, więc całej klatki nie da się tu złożyć;
 * da się za to sprawdzić **rozbiór pojedynczego prymitywu na komórki**, i to jest
 * dokładnie to, o co pyta kryterium kroku 30: czy ósmy prymityw ma w trybie
 * zapasowym odpowiednik.
 *
 * Ma, i to lepszy niż nawias narożny czy suwak, które w siatce znakowej znikają:
 * podświetlenie schodzi do **dwóch atrybutów tej samej komórki** — tła i koloru
 * pisma — więc dopasowanie widać co do znaku tak samo, jak w torze graficznym.
 */
final class TextFrameRendererTest extends TestCase
{
    use ResetsSingletons;

    protected function tearDown(): void
    {
        $this->resetSingleton(ThemeService::class);
    }

    /** Tło pod fragmentem obejmuje tyle komórek, ile fragment ma znaków. */
    public function testHighlightPaintsBackgroundAndForegroundOfItsCells(): void
    {
        $buffer = new CellBuffer(1, 10);

        $this->draw($buffer, new TextRun(0, 0, 'plik.txt', Role::Text));
        $this->draw($buffer, new TextMark(0, 0, 'pli', Role::Background, Role::Accent));

        $line = $buffer->toAnsi(new AnsiPalette(true));
        $palette = new AnsiPalette(true);
        $theme = Theme::grafit();

        // Dopasowanie zaczyna się od pary kodów: tło akcentu i pismo w kolorze
        // tła. Reszta wiersza wraca do zwykłego koloru pisma — czyli atrybut
        // kończy się dokładnie tam, gdzie kończy się fragment.
        self::assertStringContainsString(
            $palette->background($theme->accent) . $palette->foreground($theme->background) . 'pli',
            $line,
        );
        self::assertStringContainsString($palette->foreground($theme->text) . 'k.txt', $line);
    }

    /**
     * Fragment **nie zmienia treści komórki**, tylko jej atrybuty.
     *
     * To jest granica degradacji: gdyby renderer tekstowy pisał w miejsce
     * dopasowania cokolwiek innego niż to, co tam stoi, tryb zapasowy pokazywałby
     * inny plik niż tryb graficzny.
     */
    public function testHighlightLeavesTheGlyphsWhereTheyWere(): void
    {
        $withMark = new CellBuffer(1, 10);
        $plain = new CellBuffer(1, 10);

        foreach ([$withMark, $plain] as $buffer) {
            $this->draw($buffer, new TextRun(0, 0, 'plik.txt', Role::Text));
        }

        $this->draw($withMark, new TextMark(0, 0, 'pli', Role::Background, Role::Accent));

        $strip = static fn (string $line): string => (string) preg_replace('/\e\[[0-9;]*m/', '', $line);

        self::assertSame(
            $strip($plain->toAnsi(new AnsiPalette(true))),
            $strip($withMark->toAnsi(new AnsiPalette(true))),
        );
    }

    /** Prymityw poza siatką nie wywraca bufora — jak każdy inny. */
    public function testHighlightOutsideTheGridIsIgnored(): void
    {
        $buffer = new CellBuffer(1, 4);

        $this->draw($buffer, new TextMark(9, 9, 'pli', Role::Background, Role::Accent));

        self::assertStringNotContainsString('pli', $buffer->toAnsi(new AnsiPalette(true)));
    }

    private function draw(CellBuffer $buffer, TextMark|TextRun $primitive): void
    {
        $renderer = new TextFrameRenderer(new AnsiPalette(true));
        $method = new ReflectionMethod($renderer, 'draw');
        $method->invoke($renderer, $buffer, $primitive);
    }
}
