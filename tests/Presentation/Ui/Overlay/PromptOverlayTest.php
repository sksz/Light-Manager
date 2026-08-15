<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Overlay;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\Message;
use LightManager\Presentation\Ui\Overlay\PromptOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Okno o jedno słowo (krok 41): `Enter` zatwierdza, `Esc` odmawia, a pustka nie
 * jest odpowiedzią.
 */
final class PromptOverlayTest extends TestCase
{
    /** @var list<string> nazwy oddane domknięciu */
    private array $accepted = [];

    protected function setUp(): void
    {
        $this->accepted = [];
    }

    public function testTheInitialValueIsThereFromTheFirstFrame(): void
    {
        $texts = self::textsOf($this->overlay('stara.txt')->draw(new Rect(0, 0, 4, 40)));

        self::assertContains('tytul.klucz', $texts);
        self::assertContains('prompt.name', $texts, 'zachęta pola');
        self::assertContains('stara.txt', $texts, 'nazwa bieżąca jako treść początkowa');
    }

    public function testEnterHandsTheTypedNameToTheClosure(): void
    {
        $overlay = $this->overlay('a');

        $overlay->handle(KeyPress::character('b'));
        $outcome = $overlay->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(['ab'], $this->accepted);
        self::assertTrue($outcome->closes);
        self::assertSame('gotowe', $outcome->message?->text);
    }

    public function testEscapeChangesNothing(): void
    {
        $overlay = $this->overlay('a');

        $outcome = $overlay->handle(KeyPress::special(Key::Escape, "\e"));

        self::assertSame([], $this->accepted);
        self::assertTrue($outcome->closes);
        self::assertNull($outcome->message);
    }

    /** `Enter` na pustym polu nie robi nic: nie ma czego zatwierdzić. */
    public function testEnterOnAnEmptyFieldDoesNothing(): void
    {
        $overlay = $this->overlay('');

        $outcome = $overlay->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame([], $this->accepted);
        self::assertFalse($outcome->closes);
        self::assertTrue($outcome->handled);
    }

    /**
     * Ukośnik **wchodzi do pola**: okno nie ocenia nazwy w ogóle, bo o tym, co jest
     * poprawną nazwą, wie moduł (D75, rozstrzygnięcie 2). Rdzeń, który by ją
     * sprawdzał, uczyłby się, czym jest plik.
     */
    public function testTheWindowDoesNotJudgeTheName(): void
    {
        $overlay = $this->overlay('');

        $overlay->handle(KeyPress::character('a'));
        $overlay->handle(KeyPress::character('/'));
        $overlay->handle(KeyPress::character('b'));
        $overlay->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(['a/b'], $this->accepted);
    }

    /** Klawisze globalne okno przepuszcza, jak każde inne (reguła kroku 19). */
    public function testGlobalKeysArePassedThrough(): void
    {
        $overlay = $this->overlay('a');

        foreach ([Key::F10, Key::F1] as $key) {
            $outcome = $overlay->handle(KeyPress::special($key, ''));

            self::assertFalse($outcome->handled, $key->name . ' należy do klawiszy globalnych');
        }
    }

    /**
     * Długi tytuł **nie rozdmuchuje okna** — usterka zobaczona w prawdziwym
     * terminalu: „Nowy katalog w /tmp/…” z pełną ścieżką rozciągał okno pytające
     * o jedno słowo na całą szerokość klatki.
     */
    public function testALongTitleDoesNotStretchTheWindow(): void
    {
        $overlay = new PromptOverlay(
            str_repeat('bardzo-długi-tytuł-', 12),
            [],
            '',
            static fn (string $value): OverlayOutcome => OverlayOutcome::close(),
            new StubTranslator(),
        );

        $bounds = $overlay->bounds(30, 200);

        self::assertLessThanOrEqual(68, $bounds->columns, 'okno o jedno słowo zostaje wąskie');
        self::assertGreaterThan(0, $bounds->column, 'i nadal stoi pośrodku');
    }

    public function testItStandsInTheMiddleAndStaysInsideTheWindow(): void
    {
        $bounds = $this->overlay('a')->bounds(30, 100);

        self::assertSame(4, $bounds->rows);
        self::assertGreaterThan(0, $bounds->row, 'okno staje pośrodku, nie u góry');
        self::assertLessThanOrEqual(100, $bounds->columns);

        $tight = $this->overlay('a')->bounds(3, 10);

        self::assertLessThanOrEqual(3, $tight->rows);
        self::assertLessThanOrEqual(10, $tight->columns);
    }

    private function overlay(string $initial): PromptOverlay
    {
        return new PromptOverlay(
            'tytul.klucz',
            [],
            $initial,
            function (string $value): OverlayOutcome {
                $this->accepted[] = $value;

                return OverlayOutcome::close(Message::info('gotowe'));
            },
            new StubTranslator(),
        );
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
}
