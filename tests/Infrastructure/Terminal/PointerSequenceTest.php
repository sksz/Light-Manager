<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Terminal;

use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;
use LightManager\Infrastructure\Terminal\KeySequenceParser;
use LightManager\Infrastructure\Terminal\ParsedKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Rozbiór sekwencji wskaźnika w trybie SGR (krok 55).
 *
 * Osobny plik obok `KeySequenceParserTest`, choć rozbiera je ten sam parser:
 * tamten sprawdza słownik klawiszy, ten — drugą postać zdarzenia. Wspólny
 * plik kazałby każdemu przypadkowi mówić, której z dwóch dotyczy.
 */
final class PointerSequenceTest extends TestCase
{
    private KeySequenceParser $parser;

    protected function setUp(): void
    {
        $this->parser = new KeySequenceParser();
    }

    /**
     * Współrzędne przychodzą liczone **od jedynki**, a słownik liczy od zera —
     * i to jest jedyne przeliczenie, jakiego wymaga zasada „współrzędne
     * w komórkach, nigdy w pikselach”.
     */
    public function testPressCarriesTheCellCountedFromZero(): void
    {
        $event = $this->pointerOf("\e[<0;12;7M");

        self::assertSame(6, $event->row);
        self::assertSame(11, $event->column);
        self::assertSame(PointerButton::Left, $event->button);
        self::assertSame(PointerAction::Press, $event->action);
    }

    /** @return array<string, array{string, PointerButton, PointerAction}> */
    public static function sequences(): array
    {
        return [
            'lewy naciśnięty' => ["\e[<0;1;1M", PointerButton::Left, PointerAction::Press],
            'lewy zwolniony' => ["\e[<0;1;1m", PointerButton::Left, PointerAction::Release],
            'środkowy naciśnięty' => ["\e[<1;1;1M", PointerButton::Middle, PointerAction::Press],
            'prawy naciśnięty' => ["\e[<2;1;1M", PointerButton::Right, PointerAction::Press],
            'prawy zwolniony' => ["\e[<2;1;1m", PointerButton::Right, PointerAction::Release],
            // Bit 32 to ruch; numer przycisku mówi wtedy, który jest trzymany.
            'przeciągnięcie lewym' => ["\e[<32;5;5M", PointerButton::Left, PointerAction::Drag],
            'przeciągnięcie prawym' => ["\e[<34;5;5M", PointerButton::Right, PointerAction::Drag],
            // Bit 64 to kółko i **zastępuje** numer przycisku.
            'kółko w górę' => ["\e[<64;1;1M", PointerButton::Left, PointerAction::ScrollUp],
            'kółko w dół' => ["\e[<65;1;1M", PointerButton::Middle, PointerAction::ScrollDown],
        ];
    }

    #[DataProvider('sequences')]
    public function testRecognisesButtonAndAction(
        string $buffer,
        PointerButton $button,
        PointerAction $action,
    ): void {
        $event = $this->pointerOf($buffer);

        self::assertSame($button, $event->button);
        self::assertSame($action, $event->action);
    }

    /**
     * Modyfikatory wskaźnika są **tymi samymi trzema**, co przy klawiszach,
     * i przychodzą niezależnie — rozłączności z reguły 11j nie ma tu gdzie
     * zastosować, bo wskaźnik nie ma ani litery, ani nazwy.
     */
    public function testCarriesAllThreeModifiersAtOnce(): void
    {
        // 4 = Shift, 8 = Alt, 16 = Ctrl.
        $event = $this->pointerOf("\e[<28;1;1M");

        self::assertTrue($event->shift);
        self::assertTrue($event->alt);
        self::assertTrue($event->ctrl);
    }

    /** @return array<string, array{string}> */
    public static function unmappedSequences(): array
    {
        return [
            'kółko poziome w lewo' => ["\e[<66;1;1M"],
            'kółko poziome w prawo' => ["\e[<67;1;1M"],
            'przycisk boczny' => ["\e[<128;1;1M"],
        ];
    }

    /**
     * Sekwencja poprawna, ale bez pozycji w słowniku (reguła 13), musi zostać
     * **zjedzona**: zostawiona w buforze rozsypałaby się przy następnym
     * wywołaniu na osobne znaki.
     */
    #[DataProvider('unmappedSequences')]
    public function testUnmappedSequenceIsConsumedWithoutCreatingAnEvent(string $buffer): void
    {
        $parsed = $this->parser->parse($buffer);

        self::assertNotNull($parsed);
        self::assertInstanceOf(KeyPress::class, $parsed->event);
        self::assertSame(strlen($buffer), $parsed->consumedBytes);
    }

    /** @return array<string, array{string}> */
    public static function incompleteSequences(): array
    {
        return [
            'sam znacznik' => ["\e[<"],
            'bez współrzędnych' => ["\e[<0"],
            'bez wiersza' => ["\e[<0;12"],
            'bez bajtu końcowego' => ["\e[<0;12;7"],
        ];
    }

    /** Sekwencja niepełna **czeka na resztę** — tą samą drogą, którą czeka strzałka. */
    #[DataProvider('incompleteSequences')]
    public function testIncompleteSequenceWaitsForMoreBytes(string $buffer): void
    {
        self::assertNull($this->parser->parse($buffer));
    }

    /** Po upływie okna niedokończona sekwencja rozstrzyga się jak każda inna: samotnym `Esc`. */
    public function testIncompleteSequenceResolvesToEscapeAfterTimeout(): void
    {
        $parsed = $this->parser->parseAfterTimeout("\e[<0;12");

        self::assertNotNull($parsed);
        self::assertInstanceOf(KeyPress::class, $parsed->event);
        self::assertSame(1, $parsed->consumedBytes);
    }

    /** Bufor z kliknięciem i literą oddaje **najpierw kliknięcie**, w całości. */
    public function testConsumesOnlyThePointerSequenceFromALongerBuffer(): void
    {
        $parsed = $this->parser->parse("\e[<0;3;2Mq");

        self::assertNotNull($parsed);
        self::assertInstanceOf(PointerEvent::class, $parsed->event);
        self::assertSame(9, $parsed->consumedBytes);
    }

    private function pointerOf(string $buffer): PointerEvent
    {
        $parsed = $this->parser->parse($buffer);

        self::assertInstanceOf(ParsedKey::class, $parsed);
        self::assertInstanceOf(PointerEvent::class, $parsed->event);

        return $parsed->event;
    }
}
