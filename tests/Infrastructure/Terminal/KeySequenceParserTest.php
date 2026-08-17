<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Terminal;

use LightManager\Application\Dto\ClipboardText;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Infrastructure\Terminal\KeySequenceParser;
use LightManager\Infrastructure\Terminal\ParsedKey;
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
        self::assertSame($key, $this->pressOf($parsed)->key);
        self::assertSame($consumed, $parsed->consumedBytes);
        self::assertSame(substr($buffer, 0, $consumed), $this->pressOf($parsed)->raw);
    }

    public function testReadsPlainCharacter(): void
    {
        $parsed = $this->parser->parse('q');

        self::assertNotNull($parsed);
        self::assertSame(Key::Character, $this->pressOf($parsed)->key);
        self::assertSame('q', $this->pressOf($parsed)->raw);
        self::assertSame(1, $parsed->consumedBytes);
    }

    public function testReadsMultiByteCharacterAsSingleEvent(): void
    {
        $parsed = $this->parser->parse('ą');

        self::assertNotNull($parsed);
        self::assertSame(Key::Character, $this->pressOf($parsed)->key);
        self::assertSame('ą', $this->pressOf($parsed)->raw);
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
        self::assertSame(Key::Character, $this->pressOf($parsed)->key);
        self::assertSame($letter, $this->pressOf($parsed)->raw, 'raw niesie literę, nie bajt sterujący');
        self::assertTrue($this->pressOf($parsed)->ctrl);
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
        self::assertSame($key, $this->pressOf($parsed)->key);
        self::assertFalse($this->pressOf($parsed)->ctrl, 'te bajty są klawiszem nazwanym, nie skrótem z Ctrl');
    }

    public function testPlainLetterIsNotMistakenForCtrl(): void
    {
        $parsed = $this->parser->parse('d');

        self::assertNotNull($parsed);
        self::assertSame('d', $this->pressOf($parsed)->raw);
        self::assertFalse($this->pressOf($parsed)->ctrl);
    }

    public function testConsumesOnlyFirstKeyFromLongerBuffer(): void
    {
        $parsed = $this->parser->parse("\e[Aq");

        self::assertNotNull($parsed);
        self::assertSame(Key::ArrowUp, $this->pressOf($parsed)->key);
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
        self::assertSame($key, $this->pressOf($parsed)->key);
        self::assertSame($consumed, $parsed->consumedBytes);
    }

    public function testTruncatedMultiByteCharacterIsConsumedAfterTimeout(): void
    {
        $parsed = $this->parser->parseAfterTimeout("\xC4");

        self::assertNotNull($parsed);
        self::assertSame(Key::Character, $this->pressOf($parsed)->key);
        self::assertSame(1, $parsed->consumedBytes);
    }

    /**
     * `ESC` + litera to `Alt`+litera — od kroku 29, w którym słownik poznał
     * drugi modyfikator.
     *
     * Do tamtej pory ta sama para bajtów dawała samotny `Escape`, a litera
     * czekała na kolejne wywołanie. Cena zmiany jest znana i wpisana w komentarz
     * parsera: `Esc` naciśnięty tuż przed literą jest od tego samego bajtu
     * nierozróżnialny.
     */
    public function testEscapeWithLetterIsAltCombination(): void
    {
        $parsed = $this->parser->parse("\eq");

        self::assertNotNull($parsed);
        self::assertSame(Key::Character, $this->pressOf($parsed)->key);
        self::assertSame('q', $this->pressOf($parsed)->raw);
        self::assertTrue($this->pressOf($parsed)->alt);
        self::assertFalse($this->pressOf($parsed)->ctrl);
        self::assertSame(2, $parsed->consumedBytes);
    }

    /** Bajt niedrukowalny po `ESC` zostaje przy dawnej odpowiedzi: samotny `Escape`. */
    public function testEscapeWithControlByteStaysALoneEscape(): void
    {
        $parsed = $this->parser->parse("\e\e");

        self::assertNotNull($parsed);
        self::assertSame(Key::Escape, $this->pressOf($parsed)->key);
        self::assertSame(1, $parsed->consumedBytes);
    }

    /** Samotny `ESC`, po którym nic nie przyszło, nadal jest klawiszem `Escape`. */
    public function testLoneEscapeAfterTimeoutIsStillEscape(): void
    {
        $parsed = $this->parser->parseAfterTimeout("\e");

        self::assertNotNull($parsed);
        self::assertSame(Key::Escape, $this->pressOf($parsed)->key);
        self::assertFalse($this->pressOf($parsed)->alt);
        self::assertSame(1, $parsed->consumedBytes);
    }

    public function testEmptyBufferYieldsNothing(): void
    {
        self::assertNull($this->parser->parse(''));
        self::assertNull($this->parser->parseAfterTimeout(''));
    }

    /**
     * `Shift` przy klawiszu nazwanym — trzeci modyfikator słownika (krok 44).
     *
     * Kodowanie XTerma: parametr modyfikatora to `1 + maska`, bit 1 = `Shift`.
     *
     * @return array<string, array{string, Key}>
     */
    public static function shiftedSequences(): array
    {
        return [
            'Shift+Delete' => ["\e[3;2~", Key::Delete],
            'Shift+F8' => ["\e[19;2~", Key::F8],
            'Shift+strzałka w górę' => ["\e[1;2A", Key::ArrowUp],
            'Shift+strzałka w dół' => ["\e[1;2B", Key::ArrowDown],
            'Shift+F1 (CSI P)' => ["\e[1;2P", Key::F1],
            // Ctrl+Shift niesie bit Shifta, a Ctrl przy nazwach dalej się pomija.
            'Ctrl+Shift+Delete' => ["\e[3;6~", Key::Delete],
        ];
    }

    #[DataProvider('shiftedSequences')]
    public function testReadsShiftFromCsiModifier(string $buffer, Key $key): void
    {
        $parsed = $this->parser->parse($buffer);

        self::assertNotNull($parsed);
        self::assertSame($key, $this->pressOf($parsed)->key);
        self::assertTrue($this->pressOf($parsed)->shift);
        self::assertSame(strlen($buffer), $parsed->consumedBytes);
    }

    /** `Ctrl` i `Alt` przy klawiszach nazwanych pozostają odrzucane — bez znacznika. */
    public function testCtrlModifierAloneDoesNotSetShift(): void
    {
        $parsed = $this->parser->parse("\e[3;5~");

        self::assertNotNull($parsed);
        self::assertSame(Key::Delete, $this->pressOf($parsed)->key);
        self::assertFalse($this->pressOf($parsed)->shift);
    }

    public function testPlainSequenceDoesNotSetShift(): void
    {
        $parsed = $this->parser->parse("\e[3~");

        self::assertNotNull($parsed);
        self::assertFalse($this->pressOf($parsed)->shift);
    }

    /**
     * Odpowiedź o schowku w postaci z normy (`ST` = `ESC \\`) — krok 57.
     */
    public function testReadsClipboardAnswerTerminatedByStringTerminator(): void
    {
        $parsed = $this->parser->parse("\e]52;c;" . base64_encode('lm') . "\e\\");

        self::assertNotNull($parsed);
        self::assertInstanceOf(ClipboardText::class, $parsed->event);
        self::assertSame('lm', $parsed->event->text);
        self::assertSame(strlen("\e]52;c;bG0=\e\\"), $parsed->consumedBytes);
    }

    /**
     * …i w postaci starszej (`BEL`). Terminale wysyłają obie, więc parser zna
     * obie — zgadywanie, która przyjdzie, nie ma jak się udać.
     */
    public function testReadsClipboardAnswerTerminatedByBell(): void
    {
        $parsed = $this->parser->parse("\e]52;c;" . base64_encode('lm') . "\a");

        self::assertNotNull($parsed);
        self::assertInstanceOf(ClipboardText::class, $parsed->event);
        self::assertSame('lm', $parsed->event->text);
    }

    /** Pole wyboru schowka bywa puste albo echem `c` — jedno i drugie przyjmujemy. */
    public function testReadsClipboardAnswerWithoutSelectionField(): void
    {
        foreach (["\e]52;;bG0=\e\\", "\e]52;c;bG0=\e\\", "\e]52;bG0=\e\\"] as $buffer) {
            $parsed = $this->parser->parse($buffer);

            self::assertNotNull($parsed, $buffer);
            self::assertInstanceOf(ClipboardText::class, $parsed->event);
            self::assertSame('lm', $parsed->event->text, $buffer);
        }
    }

    /** Treść wielowierszowa przechodzi bez zmian — base64 nie zna wierszy. */
    public function testReadsMultilineClipboardAnswer(): void
    {
        $text = "pierwszy\ndrugi\ntrzeci";
        $parsed = $this->parser->parse("\e]52;c;" . base64_encode($text) . "\e\\");

        self::assertNotNull($parsed);
        self::assertInstanceOf(ClipboardText::class, $parsed->event);
        self::assertSame($text, $parsed->event->text);
    }

    /** Schowek pusty jest prawdziwą odpowiedzią, nie brakiem odpowiedzi. */
    public function testEmptyClipboardAnswerIsStillAnAnswer(): void
    {
        $parsed = $this->parser->parse("\e]52;c;\e\\");

        self::assertNotNull($parsed);
        self::assertInstanceOf(ClipboardText::class, $parsed->event);
        self::assertSame('', $parsed->event->text);
    }

    /**
     * **Sedno trudności kroku 57**: odpowiedź niepełna czeka na resztę i czeka
     * **także po upływie okna dosłania**.
     *
     * To jedyne miejsce, w którym `parseAfterTimeout()` nie rozstrzyga — bo
     * długość odpowiedzi zależy od zawartości schowka, a nie od protokołu. Bez
     * tego wyjątku treść dłuższa od jednego odczytu rozsypywałaby się na
     * fałszywe naciśnięcia.
     */
    public function testUnfinishedClipboardAnswerWaitsEvenAfterTimeout(): void
    {
        $partial = "\e]52;c;" . substr(base64_encode(str_repeat('x', 900)), 0, 400);

        self::assertNull($this->parser->parse($partial));
        self::assertNull($this->parser->parseAfterTimeout($partial));
    }

    /**
     * Warunek, bez którego poprzednia reguła zamurowałaby wejście: czekamy
     * **tylko na to, co się zapowiedziało pełnym znacznikiem**.
     *
     * `Alt`+`]` to dwa bajty nieodróżnialne od początku łańcucha OSC, więc bez
     * tego warunku jedno naciśnięcie klawisza zatrzymywałoby wszystkie następne
     * — w oczekiwaniu na zakończenie, którego nikt nie wysłał.
     */
    public function testAltBracketIsStillAltBracketAfterTimeout(): void
    {
        $parsed = $this->parser->parseAfterTimeout("\e]");

        self::assertNotNull($parsed);
        $press = $this->pressOf($parsed);
        self::assertSame(Key::Character, $press->key);
        self::assertSame(']', $press->raw);
        self::assertTrue($press->alt);
        self::assertSame(2, $parsed->consumedBytes);
    }

    /** Niepełny znacznik jeszcze może się nim stać, więc przed terminem czekamy. */
    public function testPartialClipboardMarkerMayStillGrow(): void
    {
        self::assertNull($this->parser->parse("\e]5"));
        self::assertNull($this->parser->parse("\e]52"));
        self::assertNull($this->parser->parse("\e]52;"));
    }

    /**
     * Ładunek, którego nie da się rozczytać, **nie jest pustym schowkiem**:
     * sekwencję zjadamy (jest domknięta), ale zdarzenia z niej nie ma. Prośba
     * wygaśnie po terminie i użytkownik usłyszy, że schowek jest nieosiągalny.
     */
    public function testUndecodableClipboardPayloadYieldsNoClipboardEvent(): void
    {
        $parsed = $this->parser->parse("\e]52;c;!!!nie-base64!!!\e\\");

        self::assertNotNull($parsed);
        self::assertSame(Key::Unknown, $this->pressOf($parsed)->key);
    }

    /** Odpowiedź w środku bufora nie zjada klawisza stojącego za nią. */
    public function testClipboardAnswerConsumesOnlyItself(): void
    {
        $parsed = $this->parser->parse("\e]52;c;bG0=\e\\x");

        self::assertNotNull($parsed);
        self::assertInstanceOf(ClipboardText::class, $parsed->event);

        $next = $this->parser->parse(substr("\e]52;c;bG0=\e\\x", $parsed->consumedBytes));

        self::assertNotNull($next);
        self::assertSame('x', $this->pressOf($next)->raw);
    }

    /**
     * Od kroku 55 rozbiór oddaje `InputEvent`, bo ta sama sekwencja CSI niesie
     * w trybie SGR także kliknięcia. Testy klawiszy pytają więc najpierw, czy
     * dostały naciśnięcie — i to samo pytanie jest ich zabezpieczeniem przed
     * pomyłką w gałęzi wskaźnika.
     */
    private function pressOf(ParsedKey $parsed): KeyPress
    {
        self::assertInstanceOf(KeyPress::class, $parsed->event);

        return $parsed->event;
    }
}
