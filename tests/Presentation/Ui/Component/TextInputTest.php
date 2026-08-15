<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Component;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\Component\TextInput;
use PHPUnit\Framework\TestCase;

final class TextInputTest extends TestCase
{
    private TextInput $input;

    protected function setUp(): void
    {
        $this->input = new TextInput();
    }

    public function testTypedCharactersLandInTheValue(): void
    {
        $this->type('core');

        self::assertSame('core', $this->input->value());
        self::assertFalse($this->input->isEmpty());
    }

    public function testMultiByteCharacterCountsAsOne(): void
    {
        $this->type('zażółć');
        $this->press(Key::Backspace);

        self::assertSame('zażół', $this->input->value());
    }

    public function testBackspaceErasesBeforeTheCaretAndDeleteUnderIt(): void
    {
        $this->type('core.help');
        $this->press(Key::Home);
        $this->press(Key::Delete);

        self::assertSame('ore.help', $this->input->value());

        $this->press(Key::End);
        $this->press(Key::Backspace);

        self::assertSame('ore.hel', $this->input->value());
    }

    /** To jest cały powód, dla którego karetka chodzi po wierszu. */
    public function testCorrectsATypoInTheMiddleWithoutErasingTheTail(): void
    {
        $this->type('core.hellp');
        $this->press(Key::ArrowLeft);
        $this->press(Key::Backspace);

        self::assertSame('core.help', $this->input->value());
    }

    public function testCaretStopsAtBothEnds(): void
    {
        $this->type('ab');

        for ($step = 0; $step < 5; ++$step) {
            $this->press(Key::ArrowLeft);
        }

        $this->type('X');

        self::assertSame('Xab', $this->input->value());

        for ($step = 0; $step < 5; ++$step) {
            $this->press(Key::ArrowRight);
        }

        $this->type('Y');

        self::assertSame('XabY', $this->input->value());
    }

    public function testBackspaceOnAnEmptyValueIsHarmless(): void
    {
        self::assertTrue($this->input->handle(KeyPress::special(Key::Backspace, '')));
        self::assertSame('', $this->input->value());
    }

    /**
     * Bez tego `Ctrl+D` wpisałby się do pola jako bajt sterujący, a skrót modułu
     * z kroku 20 nigdy by nie zadziałał.
     */
    public function testCharacterWithCtrlIsPassedUpInsteadOfBeingTyped(): void
    {
        self::assertFalse($this->input->handle(KeyPress::ctrl('d')));
        self::assertSame('', $this->input->value());
    }

    public function testUnknownKeyIsPassedUp(): void
    {
        self::assertFalse($this->input->handle(KeyPress::special(Key::F10, '')));
    }

    public function testValueFromOutsidePutsTheCaretAtTheEnd(): void
    {
        $this->input->useValue('core.jump /tmp');
        $this->type('/x');

        self::assertSame('core.jump /tmp/x', $this->input->value());
    }

    public function testDrawsPromptAndValue(): void
    {
        $this->type('core');
        $this->input->useTime(0.6);

        $texts = self::textsOf($this->input->draw(new Rect(4, 2, 1, 20)));

        self::assertSame(['> ', 'core'], $texts);
    }

    public function testCaretShowsAndHidesWithTime(): void
    {
        $this->type('core');

        $this->input->useTime(0.0);
        self::assertTrue(self::hasCaret($this->input->draw(new Rect(0, 0, 1, 20))), 'pierwsze pół sekundy — świeci');

        $this->input->useTime(0.6);
        self::assertFalse(self::hasCaret($this->input->draw(new Rect(0, 0, 1, 20))), 'drugie pół sekundy — gaśnie');

        $this->input->useTime(1.1);
        self::assertTrue(self::hasCaret($this->input->draw(new Rect(0, 0, 1, 20))));
    }

    public function testCaretStandsOnTheCharacterUnderIt(): void
    {
        $this->type('ab');
        $this->press(Key::Home);
        $this->input->useTime(0.0);

        $primitives = $this->input->draw(new Rect(0, 0, 1, 20));
        $under = array_values(array_filter(
            $primitives,
            static fn ($primitive): bool => $primitive instanceof TextRun && $primitive->role === Role::SelectionText,
        ));

        self::assertCount(1, $under);
        self::assertInstanceOf(TextRun::class, $under[0]);
        self::assertSame('a', $under[0]->text);
    }

    /** Pole na jeden wiersz nie ma innego sposobu, by pokazać koniec długiej ścieżki. */
    public function testLongValueScrollsSoThatTheCaretStaysVisible(): void
    {
        $this->type('core.jump /home/sksz/Projects/light_manager');
        $this->input->useTime(0.0);

        $texts = self::textsOf($this->input->draw(new Rect(0, 0, 1, 12)));

        self::assertSame('> ', $texts[0]);
        // Dziesięć kolumn na treść, z czego ostatnia należy do karetki stojącej
        // za końcem napisu — widać więc dziewięć znaków, zawsze tych ostatnich.
        self::assertSame(9, mb_strlen($texts[1]));
        self::assertStringEndsWith('manager', $texts[1]);
    }

    public function testDrawsNothingWhenThereIsNoRoom(): void
    {
        self::assertSame([], $this->input->draw(new Rect(0, 0, 0, 0)));
        self::assertSame([], $this->input->draw(new Rect(0, 0, 1, 1)), 'sam znak zachęty się nie mieści');
    }

    public function testClearForgetsTheValueAndTheCaret(): void
    {
        $this->type('core.help');
        $this->input->clear();
        $this->type('x');

        self::assertSame('x', $this->input->value());
        self::assertTrue((new TextInput())->isEmpty());
    }

    private function type(string $text): void
    {
        foreach (mb_str_split($text) as $character) {
            $this->input->handle(KeyPress::character($character));
        }
    }

    private function press(Key $key): void
    {
        $this->input->handle(KeyPress::special($key, ''));
    }

    /**
     * Tryb maskowany: **treść znika z rysunku, ale nie z pola** (krok 48, D87 nr 4).
     *
     * To jest cała umowa maskowania i obie jej połowy stoją tu naraz: `draw()`
     * nie wypuszcza ani jednego znaku hasła, a `value()` oddaje je w całości —
     * bo pole, które gubi treść, byłoby ozdobą.
     */
    public function testMaskedInputDrawsDotsButKeepsTheValue(): void
    {
        $masked = new TextInput('> ', masked: true);
        $masked->useValue('tajne');

        $texts = self::textsOf($masked->draw(new Rect(0, 0, 1, 20)));

        self::assertSame('tajne', $masked->value());
        self::assertContains('•••••', $texts);
        self::assertNotContains('tajne', $texts);
    }

    /**
     * Maska ma **tyle znaków, ile treść** — inaczej karetka rozjechałaby się
     * z tym, co widać, a przy haśle dłuższym od pola okno przewijania
     * pokazywałoby nie ten fragment.
     */
    public function testMaskKeepsTheLengthOfTheValue(): void
    {
        $masked = new TextInput('> ', masked: true);
        $masked->useValue('abcdefghij');

        self::assertContains(str_repeat('•', 10), self::textsOf($masked->draw(new Rect(0, 0, 1, 20))));
    }

    /** Znak pod karetką też jest zamaskowany — inaczej hasło odsłaniałoby się po literze. */
    public function testTheCharacterUnderTheCaretIsMaskedToo(): void
    {
        $masked = new TextInput('> ', masked: true);
        $masked->useValue('tajne');
        $masked->handle(KeyPress::special(Key::Home, ''));
        $masked->useTime(0.0);

        foreach ($masked->draw(new Rect(0, 0, 1, 20)) as $primitive) {
            if ($primitive instanceof TextRun && $primitive->role === Role::SelectionText) {
                self::assertSame('•', $primitive->text);

                return;
            }
        }

        self::fail('karetka nie narysowała znaku pod sobą');
    }

    /** Pole jawne zostaje jawne — maskowanie jest wyborem przy powstaniu, nie domyślnym. */
    public function testAPlainInputIsStillPlain(): void
    {
        $this->input->useValue('jawne');

        self::assertContains('jawne', self::textsOf($this->input->draw(new Rect(0, 0, 1, 20))));
    }

    /**
     * @param list<\LightManager\Application\Ui\Primitive\Primitive> $primitives
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

    /** @param list<\LightManager\Application\Ui\Primitive\Primitive> $primitives */
    private static function hasCaret(array $primitives): bool
    {
        foreach ($primitives as $primitive) {
            if ($primitive instanceof RoundRect && $primitive->fill === Role::Selection) {
                return true;
            }
        }

        return false;
    }
}
