<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Terminal;

use LightManager\Application\Dto\Key;
use LightManager\Infrastructure\Terminal\KeySequenceParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KeySequenceParserTest extends TestCase
{
    private KeySequenceParser $parser;

    protected function setUp(): void
    {
        $this->parser = new KeySequenceParser();
    }

    /** @return array<string, array{string, Key, int}> */
    public static function completeSequences(): array
    {
        return [
            'strzałka w górę (CSI)' => ["\e[A", Key::ArrowUp, 3],
            'strzałka w dół (CSI)' => ["\e[B", Key::ArrowDown, 3],
            'strzałka w prawo (CSI)' => ["\e[C", Key::ArrowRight, 3],
            'strzałka w lewo (CSI)' => ["\e[D", Key::ArrowLeft, 3],
            'strzałka w górę (SS3)' => ["\eOA", Key::ArrowUp, 3],
            'strzałka w lewo (SS3)' => ["\eOD", Key::ArrowLeft, 3],
            'strzałka z modyfikatorem' => ["\e[1;5A", Key::ArrowUp, 6],
            'Home przez CSI H' => ["\e[H", Key::Home, 3],
            'End przez CSI F' => ["\e[F", Key::End, 3],
            'Home przez tyldę' => ["\e[1~", Key::Home, 4],
            'Delete' => ["\e[3~", Key::Delete, 4],
            'End przez tyldę' => ["\e[4~", Key::End, 4],
            'Page Up' => ["\e[5~", Key::PageUp, 4],
            'Page Down' => ["\e[6~", Key::PageDown, 4],
            'Delete z modyfikatorem' => ["\e[3;5~", Key::Delete, 6],
            'Enter (CR)' => ["\r", Key::Enter, 1],
            'Enter (LF)' => ["\n", Key::Enter, 1],
            'Tab' => ["\t", Key::Tab, 1],
            'Backspace (DEL)' => ["\x7f", Key::Backspace, 1],
            'Backspace (BS)' => ["\x08", Key::Backspace, 1],
            'F1 przez SS3' => ["\eOP", Key::F1, 3],
            'F2 przez SS3' => ["\eOQ", Key::F2, 3],
            'F4 przez SS3' => ["\eOS", Key::F4, 3],
            'F1 przez tyldę' => ["\e[11~", Key::F1, 5],
            'F2 przez tyldę' => ["\e[12~", Key::F2, 5],
            'F5' => ["\e[15~", Key::F5, 5],
            'F12' => ["\e[24~", Key::F12, 5],
            'F1 z modyfikatorem' => ["\e[1;2P", Key::F1, 6],
            // Numeracja VT220 ma dziury — 16 i 22 nie należą do żadnego klawisza.
            'dziura w numeracji klawiszy funkcyjnych' => ["\e[16~", Key::Unknown, 5],
            'nieznana sekwencja CSI' => ["\e[Z", Key::Unknown, 3],
            'nieznana sekwencja SS3' => ["\eOZ", Key::Unknown, 3],
        ];
    }

    #[DataProvider('completeSequences')]
    public function testRecognisesCompleteSequenceAsSingleEvent(string $buffer, Key $key, int $consumed): void
    {
        $parsed = $this->parser->parse($buffer);

        self::assertNotNull($parsed);
        self::assertSame($key, $parsed->keyPress->key);
        self::assertSame($consumed, $parsed->consumedBytes);
        self::assertSame(substr($buffer, 0, $consumed), $parsed->keyPress->raw);
    }

    public function testReadsPlainCharacter(): void
    {
        $parsed = $this->parser->parse('q');

        self::assertNotNull($parsed);
        self::assertSame(Key::Character, $parsed->keyPress->key);
        self::assertSame('q', $parsed->keyPress->raw);
        self::assertSame(1, $parsed->consumedBytes);
    }

    public function testReadsMultiByteCharacterAsSingleEvent(): void
    {
        $parsed = $this->parser->parse('ą');

        self::assertNotNull($parsed);
        self::assertSame(Key::Character, $parsed->keyPress->key);
        self::assertSame('ą', $parsed->keyPress->raw);
        self::assertSame(2, $parsed->consumedBytes);
    }

    /** @return array<string, array{string, string}> */
    public static function controlBytes(): array
    {
        return [
            'Ctrl+A' => ["\x01", 'a'],
            'Ctrl+D' => ["\x04", 'd'],
            'Ctrl+S — wolne, bo tryb surowy wyłącza sterowanie przepływem' => ["\x13", 's'],
            'Ctrl+Z' => ["\x1a", 'z'],
        ];
    }

    #[DataProvider('controlBytes')]
    public function testReadsControlByteAsLetterWithCtrl(string $buffer, string $letter): void
    {
        $parsed = $this->parser->parse($buffer);

        self::assertNotNull($parsed);
        self::assertSame(Key::Character, $parsed->keyPress->key);
        self::assertSame($letter, $parsed->keyPress->raw, 'raw niesie literę, nie bajt sterujący');
        self::assertTrue($parsed->keyPress->ctrl);
        self::assertSame(1, $parsed->consumedBytes);
    }

    /** @return array<string, array{string, Key}> */
    public static function controlBytesTakenByNamedKeys(): array
    {
        return [
            'Ctrl+H to Backspace' => ["\x08", Key::Backspace],
            'Ctrl+I to Tab' => ["\x09", Key::Tab],
            'Ctrl+J to Enter' => ["\x0a", Key::Enter],
            'Ctrl+M to Enter' => ["\x0d", Key::Enter],
        ];
    }

    #[DataProvider('controlBytesTakenByNamedKeys')]
    public function testControlBytesSharedWithNamedKeysStayNamed(string $buffer, Key $key): void
    {
        $parsed = $this->parser->parse($buffer);

        self::assertNotNull($parsed);
        self::assertSame($key, $parsed->keyPress->key);
        self::assertFalse($parsed->keyPress->ctrl, 'te bajty są klawiszem nazwanym, nie skrótem z Ctrl');
    }

    public function testPlainLetterIsNotMistakenForCtrl(): void
    {
        $parsed = $this->parser->parse('d');

        self::assertNotNull($parsed);
        self::assertSame('d', $parsed->keyPress->raw);
        self::assertFalse($parsed->keyPress->ctrl);
    }

    public function testConsumesOnlyFirstKeyFromLongerBuffer(): void
    {
        $parsed = $this->parser->parse("\e[Aq");

        self::assertNotNull($parsed);
        self::assertSame(Key::ArrowUp, $parsed->keyPress->key);
        self::assertSame(3, $parsed->consumedBytes);
    }

    /** @return array<string, array{string}> */
    public static function incompleteSequences(): array
    {
        return [
            'samotny Escape' => ["\e"],
            'początek CSI' => ["\e["],
            'CSI bez bajtu końcowego' => ["\e[1;5"],
            'początek SS3' => ["\eO"],
            'urwany znak UTF-8' => ["\xC4"],
        ];
    }

    #[DataProvider('incompleteSequences')]
    public function testWaitsForMoreBytesWhenSequenceMayStillGrow(string $buffer): void
    {
        self::assertNull($this->parser->parse($buffer));
    }

    /** @return array<string, array{string, Key, int}> */
    public static function ambiguousSequencesAfterTimeout(): array
    {
        return [
            'samotny Escape' => ["\e", Key::Escape, 1],
            'początek CSI' => ["\e[", Key::Escape, 1],
            'CSI bez bajtu końcowego' => ["\e[1;5", Key::Escape, 1],
            'początek SS3' => ["\eO", Key::Escape, 1],
        ];
    }

    #[DataProvider('ambiguousSequencesAfterTimeout')]
    public function testResolvesAmbiguityAfterTimeout(string $buffer, Key $key, int $consumed): void
    {
        $parsed = $this->parser->parseAfterTimeout($buffer);

        self::assertNotNull($parsed);
        self::assertSame($key, $parsed->keyPress->key);
        self::assertSame($consumed, $parsed->consumedBytes);
    }

    public function testTruncatedMultiByteCharacterIsConsumedAfterTimeout(): void
    {
        $parsed = $this->parser->parseAfterTimeout("\xC4");

        self::assertNotNull($parsed);
        self::assertSame(Key::Character, $parsed->keyPress->key);
        self::assertSame(1, $parsed->consumedBytes);
    }

    public function testAltCombinationYieldsEscapeAsSeparateEvent(): void
    {
        $parsed = $this->parser->parse("\eq");

        self::assertNotNull($parsed);
        self::assertSame(Key::Escape, $parsed->keyPress->key);
        self::assertSame(1, $parsed->consumedBytes);
    }

    public function testEmptyBufferYieldsNothing(): void
    {
        self::assertNull($this->parser->parse(''));
        self::assertNull($this->parser->parseAfterTimeout(''));
    }
}
