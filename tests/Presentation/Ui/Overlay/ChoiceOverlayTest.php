<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Overlay;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\Message;
use LightManager\Presentation\Ui\Overlay\ChoiceOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Okno wyboru oddaje **identyfikator odpowiedzi** i nic ponad to — o tym, czego
 * pytanie dotyczy, nie wie (krok 42).
 */
final class ChoiceOverlayTest extends TestCase
{
    /** @var list<string> ślad odpowiedzi, które wyszły z okna */
    private array $answers = [];

    protected function setUp(): void
    {
        $this->answers = [];
    }

    public function testEnterAnswersWithTheFirstOptionBecauseFocusStartsThere(): void
    {
        $outcome = $this->overlay()->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(['nadpisz'], $this->answers);
        self::assertTrue($outcome->closes);
    }

    public function testArrowsWalkTheListAndStopAtItsEnds(): void
    {
        $overlay = $this->overlay();

        $overlay->handle(KeyPress::special(Key::ArrowDown, ''));
        $overlay->handle(KeyPress::special(Key::ArrowDown, ''));
        $overlay->handle(KeyPress::special(Key::ArrowDown, ''));
        $overlay->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(['przerwij'], $this->answers, 'strzałka w dół nie wychodzi poza ostatnią');

        $up = $this->overlay();
        $up->handle(KeyPress::special(Key::ArrowUp, ''));
        $up->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(['przerwij', 'nadpisz'], $this->answers, 'ani w górę poza pierwszą');
    }

    /**
     * `Esc` znaczy **odpowiedź ostatnią**, a nie „zamknij okno bez odpowiedzi”:
     * praca stoi i czeka, więc okno zamknięte milczkiem zostawiłoby ją stojącą
     * na zawsze.
     */
    public function testEscapeAnswersWithTheLastOption(): void
    {
        $this->overlay()->handle(KeyPress::special(Key::Escape, "\e"));

        self::assertSame(['przerwij'], $this->answers);
    }

    public function testOtherKeysGoToTheGlobalOnes(): void
    {
        $outcome = $this->overlay()->handle(KeyPress::character('x'));

        self::assertSame([], $this->answers);
        self::assertFalse($outcome->handled, 'klawisz przechodzi do klawiszy globalnych');
    }

    public function testEveryOptionIsDrawnAsItsOwnRow(): void
    {
        $texts = [];

        foreach ($this->overlay()->draw(new Rect(0, 0, 10, 40)) as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = $primitive->text;
            }
        }

        self::assertContains('nadpisz', $texts);
        self::assertContains('pomiń', $texts);
        self::assertContains('przerwij', $texts);
    }

    /** Okno rośnie o wiersz na odpowiedź i **nie rozdmuchuje się** długim tytułem. */
    public function testItStandsInTheMiddleAndKeepsItsWidth(): void
    {
        $bounds = $this->overlay()->bounds(30, 200);

        self::assertSame(6, $bounds->rows, 'trzy odpowiedzi plus oprawa');
        self::assertGreaterThan(0, $bounds->row);
        self::assertLessThanOrEqual(68, $bounds->columns);

        $wide = new ChoiceOverlay(
            str_repeat('bardzo-długi-tytuł-', 12),
            [],
            ['a' => 'a'],
            fn (string $id): OverlayOutcome => OverlayOutcome::close(),
            new StubTranslator(),
        );

        self::assertLessThanOrEqual(68, $wide->bounds(30, 200)->columns);
    }

    private function overlay(): ChoiceOverlay
    {
        return new ChoiceOverlay(
            'pytanie.klucz',
            ['name' => 'plik.txt'],
            [
                'nadpisz' => 'nadpisz',
                'pomiń' => 'pomiń',
                'przerwij' => 'przerwij',
            ],
            function (string $id): OverlayOutcome {
                $this->answers[] = $id;

                return OverlayOutcome::close(Message::info('gotowe'));
            },
            new StubTranslator(),
        );
    }
}
