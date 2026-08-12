<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;

/**
 * Zamienia zdarzenia klawiatury GLFW na słownik `KeyPress` — odpowiednik
 * `KeySequenceParser` dla okna, z pominięciem sekwencji escape, których
 * w oknie nie ma (krok 34).
 *
 * Klasa jest czysta: bez jednego wywołania GLFW, bez okna i bez stanu —
 * korzysta wyłącznie ze stałych rozszerzenia (kody klawiszy i modyfikatorów),
 * dzięki czemu daje się testować w PHPUnit bez otwierania okna.
 *
 * Podział pracy odzwierciedla podział zdarzeń GLFW: klawisze specjalne
 * i skróty `Ctrl`+litera przychodzą zdarzeniem klawisza (`mapKeyEvent()`),
 * a znaki drukowalne — osobnym zdarzeniem znaku (`mapCharacter()`), już po
 * przetłumaczeniu przez układ klawiatury. Litera bez `Ctrl` jest w zdarzeniu
 * klawisza celowo ignorowana, bo przyjdzie zdarzeniem znaku — inaczej każde
 * naciśnięcie dawałoby dwa zdarzenia.
 */
final class GlfwKeyMapper
{
    /**
     * Zdarzenie klawisza → `KeyPress`; `null`, gdy zdarzenie nie tworzy
     * naciśnięcia (puszczenie klawisza, litera czekająca na zdarzenie znaku,
     * modyfikator sam w sobie).
     *
     * Powtórka (`GLFW_REPEAT`) liczy się jak naciśnięcie — przytrzymana
     * strzałka ma przewijać, dokładnie jak w terminalu.
     *
     * Modyfikator nie zmienia klawisza bazowego (`Ctrl+Delete` to `Delete`) —
     * ta sama reguła, którą `KeySequenceParser` stosuje do parametrów
     * sekwencji. Wyjątkiem jest `Ctrl`+litera, bo tę parę słownik zna od
     * kroku 19.
     *
     * `raw` klawiszy specjalnych niesie bajt, którym klawisz przychodzi
     * z terminala (`\r`, `\t`, …), a pusty string tam, gdzie terminalowym
     * odpowiednikiem była wielobajtowa sekwencja escape — zdarzenie GLFW
     * żadnych bajtów nie ma, a żaden konsument `raw` specjalnych nie czyta.
     */
    public function mapKeyEvent(int $key, int $action, int $mods): ?KeyPress
    {
        if ($action !== GLFW_PRESS && $action !== GLFW_REPEAT) {
            return null;
        }

        $special = $this->specialKey($key);

        if ($special !== null) {
            return KeyPress::special($special, $this->rawFor($special));
        }

        if (($mods & GLFW_MOD_CONTROL) !== 0 && $key >= GLFW_KEY_A && $key <= GLFW_KEY_Z) {
            return KeyPress::ctrl(chr($key - GLFW_KEY_A + 0x61));
        }

        return null;
    }

    /**
     * Zdarzenie znaku → `KeyPress::character()`. Kod przychodzi jako punkt
     * kodowy Unicode, a słownik niesie znaki w UTF-8 — jak `KeySequenceParser`,
     * który składa znak z bajtów STDIN.
     *
     * Punkty sterujące odsiewamy na wszelki wypadek: GLFW ich nie wysyła,
     * ale zdarzenie z zerem czy `DEL` nie ma prawa stać się „znakiem”.
     */
    public function mapCharacter(int $codepoint): ?KeyPress
    {
        if ($codepoint < 0x20 || $codepoint === 0x7F) {
            return null;
        }

        return KeyPress::character($this->utf8($codepoint));
    }

    private function specialKey(int $key): ?Key
    {
        if ($key >= GLFW_KEY_F1 && $key <= GLFW_KEY_F12) {
            $functionKeys = [
                Key::F1, Key::F2, Key::F3, Key::F4, Key::F5, Key::F6,
                Key::F7, Key::F8, Key::F9, Key::F10, Key::F11, Key::F12,
            ];

            return $functionKeys[$key - GLFW_KEY_F1];
        }

        return match ($key) {
            GLFW_KEY_UP => Key::ArrowUp,
            GLFW_KEY_DOWN => Key::ArrowDown,
            GLFW_KEY_LEFT => Key::ArrowLeft,
            GLFW_KEY_RIGHT => Key::ArrowRight,
            GLFW_KEY_HOME => Key::Home,
            GLFW_KEY_END => Key::End,
            GLFW_KEY_PAGE_UP => Key::PageUp,
            GLFW_KEY_PAGE_DOWN => Key::PageDown,
            GLFW_KEY_DELETE => Key::Delete,
            GLFW_KEY_ENTER, GLFW_KEY_KP_ENTER => Key::Enter,
            GLFW_KEY_BACKSPACE => Key::Backspace,
            GLFW_KEY_TAB => Key::Tab,
            GLFW_KEY_ESCAPE => Key::Escape,
            default => null,
        };
    }

    private function rawFor(Key $key): string
    {
        return match ($key) {
            Key::Enter => "\r",
            Key::Tab => "\t",
            Key::Backspace => "\x7f",
            Key::Escape => "\e",
            default => '',
        };
    }

    private function utf8(int $codepoint): string
    {
        return match (true) {
            $codepoint < 0x80 => chr($codepoint),
            $codepoint < 0x800 => chr(0xC0 | $codepoint >> 6)
                . chr(0x80 | $codepoint & 0x3F),
            $codepoint < 0x10000 => chr(0xE0 | $codepoint >> 12)
                . chr(0x80 | $codepoint >> 6 & 0x3F)
                . chr(0x80 | $codepoint & 0x3F),
            default => chr(0xF0 | $codepoint >> 18)
                . chr(0x80 | $codepoint >> 12 & 0x3F)
                . chr(0x80 | $codepoint >> 6 & 0x3F)
                . chr(0x80 | $codepoint & 0x3F),
        };
    }
}
