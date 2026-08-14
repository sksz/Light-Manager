<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui;

use LightManager\Application\Dto\Key;
use LightManager\Presentation\Ui\FocusHint;
use LightManager\Presentation\Ui\Hint;
use LightManager\Presentation\Ui\KeyBinding;
use LightManager\Presentation\Ui\StatusHints;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Składanie stopki: kolejność, odsiew powtórzeń i ustępowanie (krok 40).
 *
 * Tłumacz oddaje klucze zamiast napisów, więc test sprawdza **rachunek**, a nie
 * brzmienie zdań — te są sprawą katalogu i pilnuje ich `TranslatorServiceTest`.
 */
final class StatusHintsTest extends TestCase
{
    public function testLevelsGoFromTheFocusedPlaceToTheKeysThatWorkEverywhere(): void
    {
        $hints = self::compose();

        self::assertSame(
            [
                'focus.pane: ↑ / ↓ move',
                'Enter open',
                '. hidden',
                'F1 help',
                'F10 quit',
            ],
            self::items($hints, 200),
        );
    }

    public function testTheNameOfThePlaceStandsBeforeItsFirstBindingOnly(): void
    {
        $items = self::items(self::compose(), 200);

        self::assertStringStartsWith('focus.pane: ', $items[0]);
        self::assertStringNotContainsString('focus.pane', implode(' ', array_slice($items, 1)));
    }

    /**
     * Ekran oddaje w `bindings()` **wszystko**, łącznie z klawiszami miejsca, bo
     * okno pomocy zostaje pełnym spisem. Bez odsiewu każdy z nich stałby w stopce
     * dwa razy.
     */
    public function testABindingDeclaredByBothTheFocusAndTheScreenAppearsOnce(): void
    {
        $items = self::items(self::compose(), 200);

        self::assertCount(1, array_filter($items, static fn (string $i): bool => str_contains($i, 'move')));
    }

    /**
     * Ten sam klawisz w dwóch różnych czynnościach zostaje **dwiema** pozycjami:
     * rozstrzygnięcie nr 4 kroku 40 wymaga zgodności klawiszy **i** klucza opisu.
     */
    public function testTheSameKeyInADifferentActionIsNotADuplicate(): void
    {
        $hints = StatusHints::compose(
            new FocusHint('focus.preview', [KeyBinding::of([Key::ArrowUp], 'scroll.line')]),
            [KeyBinding::of([Key::ArrowUp], 'move.selection')],
            [],
            new StubTranslator(),
        );

        self::assertSame(['focus.preview: ↑ scroll.line', '↑ move.selection'], self::items($hints, 200));
    }

    public function testGlobalKeysYieldFirstAndTheFocusedPlaceYieldsLast(): void
    {
        $hints = self::compose();

        self::assertSame(
            ['focus.pane: ↑ / ↓ move', 'Enter open', '. hidden', 'F1 help'],
            self::items($hints, 60),
            'ustępowanie idzie od końca — pierwsze znika F10',
        );
        self::assertSame(
            ['focus.pane: ↑ / ↓ move', 'F1 help'],
            self::items($hints, 40),
            'w ciaśniejszym oknie zostaje miejsce z ogniskiem i przypięty F1',
        );
    }

    public function testTheKeyToTheFullListIsTheLastToGo(): void
    {
        self::assertSame(['F1 help'], self::items(self::compose(), 12));
        self::assertSame([], self::items(self::compose(), 6), 'na dwa słowa nie ma miejsca — stopki nie ma');
    }

    /** Pozycja nie mieści się w całości, więc znika w całości — nigdy w połowie słowa. */
    public function testAHintIsNeverCutInHalf(): void
    {
        foreach (self::compose()->lines([21]) as $line) {
            self::assertStringNotContainsString('…', $line);
            self::assertLessThanOrEqual(21, mb_strlen($line));
        }
    }

    public function testTheSecondRowTakesWhatTheFirstCouldNotHold(): void
    {
        $lines = self::compose()->lines([30, 60]);

        self::assertCount(2, $lines);
        self::assertSame('focus.pane: ↑ / ↓ move', $lines[0]);
        self::assertSame('Enter open · . hidden · F1 help · F10 quit', $lines[1]);
    }

    /**
     * Wiersz pierwszy bywa pusty i to jest odpowiedź prawdziwa, a nie brak
     * odpowiedzi: przy długim komunikacie podpowiedzi zaczynają się dopiero
     * w drugim wierszu, a numer wiersza bierze się z położenia na liście.
     */
    public function testARowTooNarrowForAnythingStaysEmptyInsteadOfShiftingTheRest(): void
    {
        $lines = (new StatusHints([new Hint('F1 pomoc')]))->lines([3, 20]);

        self::assertSame(['', 'F1 pomoc'], $lines);
    }

    public function testFittingInOneRowIsTheQuestionThatMakesTheBarGrow(): void
    {
        $hints = self::compose();

        self::assertTrue($hints->fitInOneRow(200));
        self::assertFalse($hints->fitInOneRow(60));
    }

    public function testNothingDeclaredMeansNoStatusHintsAtAll(): void
    {
        $hints = StatusHints::compose(null, [], [], new StubTranslator());

        self::assertTrue($hints->isEmpty());
        self::assertSame([], $hints->lines([100]));
    }

    /**
     * Trzy poziomy w takim kształcie, w jakim widzi je przeglądarka: panel
     * z ogniskiem, ekran (powtarzający wiązania panelu) i klawisze rdzenia.
     */
    private static function compose(): StatusHints
    {
        $move = KeyBinding::of([Key::ArrowUp, Key::ArrowDown], 'move');
        $open = KeyBinding::of([Key::Enter], 'open');

        return StatusHints::compose(
            new FocusHint('focus.pane', [$move, $open]),
            [$move, $open, KeyBinding::character('.', 'hidden')],
            [KeyBinding::of([Key::F1], 'help'), KeyBinding::of([Key::F10], 'quit')],
            new StubTranslator(),
        );
    }

    /** @return list<string> */
    private static function items(StatusHints $hints, int $columns): array
    {
        $lines = $hints->lines([$columns]);

        return $lines === [] || $lines[0] === '' ? [] : explode(' · ', $lines[0]);
    }
}
