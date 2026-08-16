<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Infrastructure\LogFrameReader;
use PHPUnit\Framework\TestCase;

/**
 * Rozbieranie strumienia logów — **na próbkach bajtów, nigdy na żywym demonie**
 * (krok 51).
 *
 * Kształt ramki nie jest tu zgadnięty: pochodzi z odpowiedzi prawdziwego demona
 * obejrzanej przed napisaniem czytnika (`01 00 00 00 00 00 00 67` — strumień 1,
 * trzy bajty wypełnienia, długość 103). Test powtarza go bajt w bajt, dzięki
 * czemu sprawdza dokładnie to, co przychodzi z gniazda, i nie potrzebuje ani
 * jednego kontenera.
 */
final class LogFrameReaderTest extends TestCase
{
    public function testFramedStreamLosesItsHeaders(): void
    {
        $reader = new LogFrameReader();

        $lines = $reader->push(self::frame(1, "pierwszy\n") . self::frame(2, "drugi\n"));

        self::assertSame(['pierwszy', 'drugi'], $lines);
    }

    /**
     * **Ramka przecięta w połowie czeka na resztę.** Porcja przychodzi z gniazda
     * w kawałkach dowolnej wielkości, więc to jest przypadek zwykły, nie skrajny.
     */
    public function testAFrameCutInHalfWaitsForTheRest(): void
    {
        $reader = new LogFrameReader();
        $frame = self::frame(1, "podzielony\n");

        self::assertSame([], $reader->push(substr($frame, 0, 5)), 'sam kawałek nagłówka to jeszcze nic');
        self::assertSame([], $reader->push(substr($frame, 5, 6)), 'nagłówek bez treści to nadal nic');
        self::assertSame(['podzielony'], $reader->push(substr($frame, 11)));
    }

    /** Kontener z TTY przysyła ten sam strumień **bez ramek** — i ma działać tak samo. */
    public function testStreamWithoutFramesIsReadAsPlainText(): void
    {
        $reader = new LogFrameReader();

        self::assertSame(['pierwszy', 'drugi'], $reader->push("pierwszy\ndrugi\n"));
    }

    /**
     * Wiersz bez znaku nowej linii **czeka**, a `flush()` go oddaje.
     *
     * To zwykle **ten najważniejszy** wiersz: komunikat, po którym proces padł.
     */
    public function testTheLastLineWithoutANewlineArrivesOnFlush(): void
    {
        $reader = new LogFrameReader();

        self::assertSame(['pierwszy'], $reader->push(self::frame(1, "pierwszy\nurwany")));
        self::assertSame('urwany', $reader->flush());
        self::assertNull($reader->flush(), 'drugie wywołanie nie ma już czego oddać');
    }

    /**
     * Sekwencje ANSI **nie wychodzą na klatkę**: wypisane wprost przestawiłyby
     * renderer w stan, którego nikt nie zamawiał. Ta sama zasada, co w podglądzie
     * tekstu z kroku 29.
     */
    public function testControlSequencesBecomeDots(): void
    {
        $reader = new LogFrameReader();

        $lines = $reader->push(self::frame(1, "\e[31mczerwony\e[0m\n"));

        self::assertSame(['.[31mczerwony.[0m'], $lines);
    }

    /** Tabulator zostaje — jest znakiem treści, a nie sterowania wyglądem. */
    public function testTabulatorSurvives(): void
    {
        $reader = new LogFrameReader();

        self::assertSame(["kolumna\twartość"], $reader->push(self::frame(1, "kolumna\twartość\n")));
    }

    /**
     * Bajty spoza UTF-8 **nie kasują wiersza**.
     *
     * To jest pułapka `preg_replace()` z modyfikatorem `u`: na treści
     * niepoprawnej wraca `null`, więc wiersz zniknąłby w całości zamiast stracić
     * jeden znak. Log jest strumieniem bajtów w kodowaniu, którego nikt nie
     * deklarował, więc przypadek jest zwykły.
     */
    public function testInvalidUtf8DoesNotSwallowTheLine(): void
    {
        $reader = new LogFrameReader();

        $lines = $reader->push(self::frame(1, "przed \xff\xfe po\n"));

        self::assertCount(1, $lines);
        self::assertStringContainsString('przed', $lines[0]);
        self::assertStringContainsString('po', $lines[0]);
        self::assertTrue(mb_check_encoding($lines[0], 'UTF-8'), 'wiersz ma nadawać się do narysowania');
    }

    /** Ramka multipleksera: bajt strumienia, trzy wypełniające, cztery długości. */
    private static function frame(int $stream, string $payload): string
    {
        return pack('CCCCN', $stream, 0, 0, 0, strlen($payload)) . $payload;
    }
}
