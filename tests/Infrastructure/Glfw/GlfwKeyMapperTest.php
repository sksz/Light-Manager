<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Glfw;

use LightManager\Application\Dto\Key;
use LightManager\Infrastructure\Glfw\GlfwKeyMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Maper jest czysty, więc test nie otwiera okna — korzysta wyłącznie ze
 * stałych rozszerzenia, dokładnie jak sam maper.
 */
final class GlfwKeyMapperTest extends TestCase
{
    /**
     * Wartość `GLFW_RELEASE`. Literał zamiast stałej rozszerzenia, bo stuby
     * `phpgl/ide-stubs` definiują ją sama przez siebie i analiza statyczna
     * nie zna jej typu.
     */
    private const RELEASE = 0;

    private GlfwKeyMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new GlfwKeyMapper();
    }

    /** @return array<string, array{int, Key}> */
    public static function specialKeys(): array
    {
        return [
            'strzałka w górę' => [GLFW_KEY_UP, Key::ArrowUp],
            'strzałka w dół' => [GLFW_KEY_DOWN, Key::ArrowDown],
            'strzałka w lewo' => [GLFW_KEY_LEFT, Key::ArrowLeft],
            'strzałka w prawo' => [GLFW_KEY_RIGHT, Key::ArrowRight],
            'Home' => [GLFW_KEY_HOME, Key::Home],
            'End' => [GLFW_KEY_END, Key::End],
            'Page Up' => [GLFW_KEY_PAGE_UP, Key::PageUp],
            'Page Down' => [GLFW_KEY_PAGE_DOWN, Key::PageDown],
            'Delete' => [GLFW_KEY_DELETE, Key::Delete],
            'Enter' => [GLFW_KEY_ENTER, Key::Enter],
            'Enter z klawiatury numerycznej' => [GLFW_KEY_KP_ENTER, Key::Enter],
            'Backspace' => [GLFW_KEY_BACKSPACE, Key::Backspace],
            'Tab' => [GLFW_KEY_TAB, Key::Tab],
            'Escape' => [GLFW_KEY_ESCAPE, Key::Escape],
            'F1' => [GLFW_KEY_F1, Key::F1],
            'F2' => [GLFW_KEY_F2, Key::F2],
            'F3' => [GLFW_KEY_F3, Key::F3],
            'F4' => [GLFW_KEY_F4, Key::F4],
            'F5' => [GLFW_KEY_F5, Key::F5],
            'F6' => [GLFW_KEY_F6, Key::F6],
            'F7' => [GLFW_KEY_F7, Key::F7],
            'F8' => [GLFW_KEY_F8, Key::F8],
            'F9' => [GLFW_KEY_F9, Key::F9],
            'F10' => [GLFW_KEY_F10, Key::F10],
            'F11' => [GLFW_KEY_F11, Key::F11],
            'F12' => [GLFW_KEY_F12, Key::F12],
        ];
    }

    #[DataProvider('specialKeys')]
    public function testMapsSpecialKeysOnPress(int $glfwKey, Key $expected): void
    {
        $press = $this->mapper->mapKeyEvent($glfwKey, GLFW_PRESS, 0);

        self::assertNotNull($press);
        self::assertSame($expected, $press->key);
        self::assertFalse($press->ctrl);
    }

    #[DataProvider('specialKeys')]
    public function testRepeatCountsAsPress(int $glfwKey, Key $expected): void
    {
        self::assertSame($expected, $this->mapper->mapKeyEvent($glfwKey, GLFW_REPEAT, 0)?->key);
    }

    #[DataProvider('specialKeys')]
    public function testReleaseProducesNothing(int $glfwKey, Key $expected): void
    {
        self::assertNull($this->mapper->mapKeyEvent($glfwKey, self::RELEASE, 0));
    }

    /** Modyfikator nie zmienia klawisza bazowego — jak w parserze sekwencji (Ctrl+Delete to Delete). */
    public function testModifierDoesNotChangeSpecialKey(): void
    {
        $press = $this->mapper->mapKeyEvent(GLFW_KEY_DELETE, GLFW_PRESS, GLFW_MOD_CONTROL);

        self::assertSame(Key::Delete, $press?->key);
        self::assertFalse($press->ctrl);
    }

    public function testCtrlLetterComesFromKeyEventAsLowercaseLetter(): void
    {
        $press = $this->mapper->mapKeyEvent(GLFW_KEY_D, GLFW_PRESS, GLFW_MOD_CONTROL);

        self::assertNotNull($press);
        self::assertSame(Key::Character, $press->key);
        self::assertSame('d', $press->raw);
        self::assertTrue($press->ctrl);
    }

    public function testEveryLetterMapsUnderCtrl(): void
    {
        foreach (range(GLFW_KEY_A, GLFW_KEY_Z) as $key) {
            $press = $this->mapper->mapKeyEvent($key, GLFW_PRESS, GLFW_MOD_CONTROL);

            self::assertSame(chr($key - GLFW_KEY_A + 0x61), $press?->raw);
            self::assertTrue($press->ctrl);
        }
    }

    /** Litera bez Ctrl przyjdzie zdarzeniem znaku — zdarzenie klawisza ma milczeć, inaczej naciśnięcie by się podwoiło. */
    public function testPlainLetterKeyEventIsIgnored(): void
    {
        self::assertNull($this->mapper->mapKeyEvent(GLFW_KEY_Q, GLFW_PRESS, 0));
    }

    /** `Alt`+litera — druga para modyfikatora, od kroku 29 (podgląd tekstu). */
    public function testAltLetterComesFromKeyEventAsLowercaseLetter(): void
    {
        $press = $this->mapper->mapKeyEvent(GLFW_KEY_Z, GLFW_PRESS, GLFW_MOD_ALT);

        self::assertNotNull($press);
        self::assertSame(Key::Character, $press->key);
        self::assertSame('z', $press->raw);
        self::assertTrue($press->alt);
        self::assertFalse($press->ctrl);
    }

    /**
     * `Ctrl` wygrywa z `Alt`, bo słownik zna modyfikatory rozłącznie, a skróty
     * modułów wiszą na `Ctrl`+literze od kroku 20.
     */
    public function testCtrlWinsOverAltWhenBothAreHeld(): void
    {
        $press = $this->mapper->mapKeyEvent(GLFW_KEY_Z, GLFW_PRESS, GLFW_MOD_CONTROL | GLFW_MOD_ALT);

        self::assertTrue($press?->ctrl);
        self::assertFalse($press->alt);
    }

    public function testAltWithNonLetterIsIgnored(): void
    {
        self::assertNull($this->mapper->mapKeyEvent(GLFW_KEY_1, GLFW_PRESS, GLFW_MOD_ALT));
    }

    public function testCtrlWithNonLetterIsIgnored(): void
    {
        self::assertNull($this->mapper->mapKeyEvent(GLFW_KEY_1, GLFW_PRESS, GLFW_MOD_CONTROL));
    }

    public function testModifierKeyAloneIsIgnored(): void
    {
        self::assertNull($this->mapper->mapKeyEvent(GLFW_KEY_LEFT_CONTROL, GLFW_PRESS, GLFW_MOD_CONTROL));
    }

    /** @return array<string, array{int, string}> */
    public static function characters(): array
    {
        return [
            'znak ASCII' => [0x71, 'q'],
            'wielka litera' => [0x51, 'Q'],
            'spacja' => [0x20, ' '],
            'polski ogonek (dwa bajty)' => [0x105, 'ą'],
            'znak trzybajtowy' => [0x20AC, '€'],
            'emoji (cztery bajty)' => [0x1F600, '😀'],
        ];
    }

    #[DataProvider('characters')]
    public function testMapsCharacterCodepointsToUtf8(int $codepoint, string $expected): void
    {
        $press = $this->mapper->mapCharacter($codepoint);

        self::assertNotNull($press);
        self::assertSame(Key::Character, $press->key);
        self::assertSame($expected, $press->raw);
        self::assertFalse($press->ctrl);
    }

    public function testControlCodepointsAreRejected(): void
    {
        self::assertNull($this->mapper->mapCharacter(0x00));
        self::assertNull($this->mapper->mapCharacter(0x1B));
        self::assertNull($this->mapper->mapCharacter(0x7F));
    }
}
