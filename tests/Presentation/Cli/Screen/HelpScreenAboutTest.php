<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Cli\Screen;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Cli\Bootstrap;
use LightManager\Presentation\Cli\Screen\HelpScreen;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Zakładka „Aplikacja” — wersja, tryb renderowania i (od kroku 37) gęstość
 * wyświetlacza.
 *
 * Skala treści jest jedyną rzeczą tamtego kroku, której **nie dało się sprawdzić
 * na maszynie projektu**: ma tam wartość 1.0. Dlatego nie przelicza niczego,
 * tylko pokazuje się użytkownikowi — a ten test pilnuje, żeby wiersz pojawiał
 * się wyłącznie wtedy, gdy jest co pokazać. W terminalu nie ma go kto zmierzyć,
 * więc pomoc ma o nim milczeć, zamiast pisać „nie dotyczy”.
 */
final class HelpScreenAboutTest extends TestCase
{
    public function testDisplayScaleShowsUpWhenSomebodyMeasuredIt(): void
    {
        $rows = $this->aboutRows('OpenGl', '1,00 × 1,00');

        self::assertContains('help.about.scale', $rows);
        self::assertContains('1,00 × 1,00', $rows);
    }

    public function testWithoutAWindowTheRowIsAbsentInsteadOfEmpty(): void
    {
        $rows = $this->aboutRows('Sixel', null);

        self::assertNotContains('help.about.scale', $rows);
        self::assertContains('help.about.version', $rows, 'pozostałe wiersze zakładki zostają');
    }

    /** @return list<string> */
    private function aboutRows(string $renderer, ?string $scale): array
    {
        $help = new HelpScreen(
            new InMemorySettings(),
            new StubTranslator(),
            Bootstrap::VERSION,
            $renderer,
            $scale,
        );

        // Zakładka „Aplikacja” jest drugą — pierwszą jest „Sterowanie”.
        $help->handle(KeyPress::special(Key::ArrowRight, ''));

        return self::textsOf($help->draw(new Rect(0, 2, 20, 60)));
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<string>
     */
    private static function textsOf(array $primitives): array
    {
        $texts = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }
}
