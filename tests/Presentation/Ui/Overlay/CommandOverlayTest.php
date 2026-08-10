<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Overlay;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandHistory;
use LightManager\Application\Command\CommandLineParser;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\Overlay\CommandOverlay;
use LightManager\Tests\Support\FakeCommand;
use LightManager\Tests\Support\InMemoryCommandHistory;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

final class CommandOverlayTest extends TestCase
{
    private CommandOverlay $overlay;

    private FakeCommand $theme;

    private InMemoryCommandHistory $stored;

    protected function setUp(): void
    {
        $registry = new CommandRegistry();
        $this->theme = new FakeCommand(
            'core.theme',
            [new CommandArgument('theme', 'command.argument.theme', suggestions: SuggestionSource::Fixed)],
            ['grafit', 'granat'],
        );

        $registry->add('core', [
            new FakeCommand('core.help'),
            new FakeCommand('core.quit', outcome: CommandOutcome::quit()),
            new FakeCommand('core.settings', outcome: CommandOutcome::opens('settings')),
            $this->theme,
        ]);

        $this->stored = new InMemoryCommandHistory(['core.help']);
        $translator = new StubTranslator();

        $this->overlay = new CommandOverlay(
            $registry,
            new CommandLineParser($translator),
            new CommandHistory($this->stored),
            $translator,
        );
        $this->overlay->prepare();
    }

    public function testEmptyFieldShowsHistoryFirstAndThenEveryCommand(): void
    {
        self::assertSame(
            ['core.help', 'core.help', 'core.quit', 'core.settings', 'core.theme'],
            $this->visibleValues(),
            'pierwszy wiersz to wpis historii, reszta to komplet komend',
        );
    }

    public function testTypingFiltersTheListInPlace(): void
    {
        $this->type('core.s');

        self::assertSame(['core.settings'], $this->visibleValues());
    }

    public function testUnknownPrefixLeavesTheListEmpty(): void
    {
        $this->type('nic');

        self::assertSame([], $this->visibleValues());
    }

    public function testTabCompletesTheCommonPrefix(): void
    {
        $this->type('core.');
        $this->press(Key::Tab);

        // Wszystkie cztery zaczynają się od `core.`, więc wspólnego nie przybywa.
        self::assertSame('core.', $this->value());

        $this->type('s');
        $this->press(Key::Tab);

        self::assertSame('core.settings', $this->value());
    }

    public function testTabOnAnEmptyFieldDoesNothing(): void
    {
        $this->press(Key::Tab);

        self::assertSame('', $this->value());
    }

    public function testArgumentsAreSuggestedFromTheCommandsOwnList(): void
    {
        $this->type('core.theme ');

        self::assertSame(['grafit', 'granat'], $this->visibleValues());

        $this->type('grafi');

        self::assertSame(['grafit'], $this->visibleValues());
    }

    public function testTabCompletesArgumentValuesToo(): void
    {
        $this->type('core.theme gra');
        $this->press(Key::Tab);

        self::assertSame('core.theme gra', $this->value(), 'wspólny przedrostek obu wartości to `gra`');

        $this->type('f');
        $this->press(Key::Tab);

        self::assertSame('core.theme grafit', $this->value());
    }

    public function testEnterRunsTheTypedLine(): void
    {
        $this->type('core.theme grafit');
        $outcome = $this->press(Key::Enter);

        self::assertTrue($outcome->handled);
        self::assertTrue($outcome->closes);
        self::assertNotNull($this->theme->received);
        self::assertSame('grafit', $this->theme->received->text('theme'));
    }

    public function testEnterRunsThePickedSuggestionWhenTheSelectionWasMoved(): void
    {
        $this->type('core.');
        $this->press(Key::ArrowDown);
        $this->press(Key::ArrowDown);
        $outcome = $this->press(Key::Enter);

        self::assertTrue($outcome->closes);
        self::assertSame('settings', $outcome->screenId, 'trzecia pozycja listy to `core.settings`');
    }

    public function testEnterOnAnUntouchedListStillRunsWhatWasTyped(): void
    {
        $this->type('core.quit');
        $outcome = $this->press(Key::Enter);

        self::assertTrue($outcome->quits);
    }

    public function testPickedHistoryEntryReplacesTheWholeLine(): void
    {
        $this->press(Key::ArrowDown);
        $this->press(Key::ArrowUp);
        $outcome = $this->press(Key::Enter);

        self::assertTrue($outcome->closes, 'pierwszy wiersz to wpis historii `core.help`');
    }

    public function testUnknownNameKeepsTheWindowOpenWithWhatWasTyped(): void
    {
        $this->type('core.nothing');
        $outcome = $this->press(Key::Enter);

        self::assertTrue($outcome->handled);
        self::assertFalse($outcome->closes, 'literówka nie ma kosztować przepisania wiersza');
        self::assertNotNull($outcome->message);
        self::assertSame('core.nothing', $this->value());
    }

    public function testMissingArgumentKeepsTheWindowOpenToo(): void
    {
        $this->type('core.theme');
        $outcome = $this->press(Key::Enter);

        self::assertFalse($outcome->closes);
        self::assertNotNull($outcome->message);
        self::assertNull($this->theme->received, 'komenda z brakiem argumentu nie została wywołana');
    }

    public function testRunLineGoesToHistoryAndComesBackNextTime(): void
    {
        $this->type('core.theme grafit');
        $this->press(Key::Enter);

        self::assertSame(
            ['core.theme grafit', 'core.help', 'core.help', 'core.quit', 'core.settings', 'core.theme'],
            $this->visibleValues(),
            'uruchomiony wiersz staje na czele historii, a ta na czele listy',
        );
    }

    public function testRejectedLineIsNotRemembered(): void
    {
        $this->type('core.nothing');
        $this->press(Key::Enter);
        $this->press(Key::Escape);

        self::assertSame(['core.help', 'core.help', 'core.quit', 'core.settings', 'core.theme'], $this->visibleValues());
    }

    public function testEscapeClosesAndForgetsTheTypedLine(): void
    {
        $this->type('core.he');
        $outcome = $this->press(Key::Escape);

        self::assertTrue($outcome->closes);
        self::assertSame('', $this->value());
    }

    /** Ten sam klawisz otwiera okno i je zamyka — jak `F1` i `F2` dla ekranów. */
    public function testF12ClosesTheWindow(): void
    {
        self::assertTrue($this->press(Key::F12)->closes);
    }

    /** Okno przepuszcza to, czego nie rozumie — na to czekają klawisze globalne. */
    public function testKeyThatBelongsToNobodyIsPassedUp(): void
    {
        $outcome = $this->press(Key::F10);

        self::assertFalse($outcome->handled);
    }

    public function testWindowStandsAboveTheStatusBarAndTakesTheFullWidth(): void
    {
        $bounds = $this->overlay->bounds(30, 80);

        self::assertSame(0, $bounds->column);
        self::assertSame(80, $bounds->columns);
        // Pasek stanu przy trzydziestu wierszach jest panelem na trzy wiersze.
        self::assertSame(26, $bounds->bottom());
    }

    public function testWindowGrowsWithTheListButNeverPastHalfTheScreen(): void
    {
        $short = $this->overlay->bounds(30, 80);

        $this->type('core.s');
        $tall = $this->overlay->bounds(30, 80);

        self::assertSame(8, $short->rows, 'pięć podpowiedzi, wiersz pola i obwódka');
        self::assertSame(4, $tall->rows, 'jedna podpowiedź — okno maleje');
        self::assertLessThanOrEqual(30, $this->overlay->bounds(6, 80)->rows);
    }

    public function testDrawsTheListAndTheFieldTogether(): void
    {
        $this->type('core.s');
        $this->overlay->useTime(0.6);

        $texts = self::textsOf($this->overlay->draw(new Rect(20, 0, 4, 40)));

        self::assertContains('core.settings', $texts, 'podpowiedź stoi nad polem');
        self::assertSame(['> ', 'core.s'], array_slice($texts, -2), 'a pole na samym dole okna');
    }

    /**
     * Wartości widoczne na liście podpowiedzi.
     *
     * Okno jest w teście szerokie, żeby nic się nie przycięło: przedmiotem
     * sprawdzenia jest **zawartość** listy, a przycinanie napisu ma własny test
     * przy komponencie `Label`.
     *
     * @return list<string>
     */
    private function visibleValues(): array
    {
        $bounds = $this->overlay->bounds(40, 60);
        $primitives = $this->overlay->draw($bounds);
        $values = [];

        foreach ($primitives as $primitive) {
            if (!$primitive instanceof TextRun) {
                continue;
            }

            // Wiersz pola zaczyna się od znaku zachęty; opisy stoją po prawej,
            // więc liczy się tylko to, co zaczyna się w pierwszej kolumnie treści.
            if ($primitive->text === '> ' || $primitive->column !== $bounds->column + 2) {
                continue;
            }

            $values[] = $primitive->text;
        }

        return $values;
    }

    private function value(): string
    {
        $this->overlay->useTime(0.6);
        $texts = self::textsOf($this->overlay->draw($this->overlay->bounds(40, 60)));
        $last = array_slice($texts, -1)[0] ?? '';

        return $last === '> ' ? '' : $last;
    }

    private function type(string $text): void
    {
        foreach (mb_str_split($text) as $character) {
            $this->overlay->handle(KeyPress::character($character));
        }
    }

    private function press(Key $key): \LightManager\Presentation\Ui\OverlayOutcome
    {
        return $this->overlay->handle(KeyPress::special($key, ''));
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
