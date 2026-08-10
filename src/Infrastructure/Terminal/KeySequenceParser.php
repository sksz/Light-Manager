<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;

/**
 * Zamienia surowe bajty ze STDIN na pojedyncze zdarzenia klawiszowe.
 *
 * Klasa jest czysta (bez I/O i bez stanu) — cała wiedza o sekwencjach escape
 * siedzi tutaj, dzięki czemu daje się testować bez terminala.
 */
final class KeySequenceParser
{
    private const ESCAPE = "\e";

    /**
     * Bajt kończący sekwencję CSI/SS3 → klawisz.
     *
     * `P`–`S` to F1–F4 w postaci SS3 (`ESC O P`), którą wysyła XTerm w trybie
     * domyślnym. Ta sama tablica obsługuje wariant z modyfikatorem
     * (`ESC [ 1 ; 2 P` = Shift+F1), bo parametry i tak są odrzucane.
     */
    private const FINAL_BYTE_KEYS = [
        'A' => Key::ArrowUp,
        'B' => Key::ArrowDown,
        'C' => Key::ArrowRight,
        'D' => Key::ArrowLeft,
        'H' => Key::Home,
        'F' => Key::End,
        'P' => Key::F1,
        'Q' => Key::F2,
        'R' => Key::F3,
        'S' => Key::F4,
    ];

    /**
     * Pierwszy parametr sekwencji `ESC [ <n> ~` → klawisz.
     *
     * Numeracja klawiszy funkcyjnych ma dziury (brak 16 i 22) — to nie pomyłka,
     * lecz historia terminala VT220, w której te kody przypadły klawiszom
     * nieistniejącym na dzisiejszych klawiaturach.
     */
    private const TILDE_KEYS = [
        '1' => Key::Home,
        '3' => Key::Delete,
        '4' => Key::End,
        '5' => Key::PageUp,
        '6' => Key::PageDown,
        '7' => Key::Home,
        '8' => Key::End,
        '11' => Key::F1,
        '12' => Key::F2,
        '13' => Key::F3,
        '14' => Key::F4,
        '15' => Key::F5,
        '17' => Key::F6,
        '18' => Key::F7,
        '19' => Key::F8,
        '20' => Key::F9,
        '21' => Key::F10,
        '23' => Key::F11,
        '24' => Key::F12,
    ];

    /**
     * Zwraca `null`, gdy bufor może być początkiem dłuższej, jeszcze
     * niekompletnej sekwencji — wtedy warto poczekać na kolejne bajty.
     */
    public function parse(string $buffer): ?ParsedKey
    {
        return $this->parseBuffer($buffer, true);
    }

    /**
     * Wersja rozstrzygająca: wywoływana, gdy terminal nie dosłał już nic
     * więcej, więc niejednoznaczności trzeba zamknąć (samotny `ESC` to
     * naciśnięcie klawisza Escape, a nie początek sekwencji).
     */
    public function parseAfterTimeout(string $buffer): ?ParsedKey
    {
        return $this->parseBuffer($buffer, false);
    }

    private function parseBuffer(string $buffer, bool $mayGrow): ?ParsedKey
    {
        if ($buffer === '') {
            return null;
        }

        if ($buffer[0] === self::ESCAPE) {
            return $this->parseEscapeSequence($buffer, $mayGrow);
        }

        return $this->parseSingleByteKey($buffer, $mayGrow);
    }

    private function parseSingleByteKey(string $buffer, bool $mayGrow): ?ParsedKey
    {
        $byte = $buffer[0];

        $named = match ($byte) {
            "\r", "\n" => Key::Enter,
            "\t" => Key::Tab,
            "\x7f", "\x08" => Key::Backspace,
            default => null,
        };

        if ($named !== null) {
            return new ParsedKey(KeyPress::special($named, $byte), 1);
        }

        $control = $this->controlLetter($byte);

        if ($control !== null) {
            return new ParsedKey(KeyPress::ctrl($control), 1);
        }

        $length = $this->utf8SequenceLength(ord($byte));
        $available = strlen($buffer);

        if ($length > $available) {
            if ($mayGrow) {
                return null;
            }

            // Terminal urwał znak wielobajtowy — oddajemy tyle, ile przyszło,
            // zamiast blokować bufor w oczekiwaniu na resztę, która nie nadejdzie.
            return new ParsedKey(KeyPress::character($buffer), $available);
        }

        return new ParsedKey(KeyPress::character(substr($buffer, 0, $length)), $length);
    }

    private function parseEscapeSequence(string $buffer, bool $mayGrow): ?ParsedKey
    {
        $length = strlen($buffer);

        if ($length === 1) {
            return $mayGrow ? null : $this->loneEscape();
        }

        return match ($buffer[1]) {
            '[' => $this->parseControlSequence($buffer, $mayGrow),
            'O' => $this->parseSingleShift($buffer, $mayGrow),
            // ESC + dowolny inny bajt (np. Alt+znak) — Escape jest osobnym
            // zdarzeniem, następny bajt zostanie odczytany przy kolejnym wywołaniu.
            default => $this->loneEscape(),
        };
    }

    private function parseSingleShift(string $buffer, bool $mayGrow): ?ParsedKey
    {
        if (strlen($buffer) < 3) {
            return $mayGrow ? null : $this->loneEscape();
        }

        $key = self::FINAL_BYTE_KEYS[$buffer[2]] ?? Key::Unknown;

        return new ParsedKey(KeyPress::special($key, substr($buffer, 0, 3)), 3);
    }

    private function parseControlSequence(string $buffer, bool $mayGrow): ?ParsedKey
    {
        $length = strlen($buffer);
        $position = 2;
        $parameters = '';

        while ($position < $length && $this->isParameterByte($buffer[$position])) {
            $parameters .= $buffer[$position];
            ++$position;
        }

        if ($position >= $length) {
            return $mayGrow ? null : $this->loneEscape();
        }

        $finalByte = $buffer[$position];
        $consumed = $position + 1;
        $raw = substr($buffer, 0, $consumed);

        $key = $finalByte === '~'
            ? self::TILDE_KEYS[$this->firstParameter($parameters)] ?? Key::Unknown
            : self::FINAL_BYTE_KEYS[$finalByte] ?? Key::Unknown;

        return new ParsedKey(KeyPress::special($key, $raw), $consumed);
    }

    private function isParameterByte(string $byte): bool
    {
        return ($byte >= '0' && $byte <= '9') || $byte === ';' || $byte === '?';
    }

    /** Modyfikatory (`ESC [ 3 ; 5 ~` = Ctrl+Delete) nie zmieniają klawisza bazowego. */
    private function firstParameter(string $parameters): string
    {
        $separator = strpos($parameters, ';');

        return $separator === false ? $parameters : substr($parameters, 0, $separator);
    }

    private function loneEscape(): ParsedKey
    {
        return new ParsedKey(KeyPress::special(Key::Escape, self::ESCAPE), 1);
    }

    /**
     * Litera kryjąca się za bajtem sterującym: `0x01` to `Ctrl+A`, `0x1A` to
     * `Ctrl+Z`. `null`, gdy bajt nie jest znakiem sterującym.
     *
     * Cztery bajty z tego przedziału **nie trafiają tutaj**, bo rozpoznaje je
     * wcześniejszy `match` na nazwane klawisze: `0x08` (`Ctrl+H`) to Backspace,
     * `0x09` (`Ctrl+I`) to Tab, a `0x0A` i `0x0D` (`Ctrl+J`, `Ctrl+M`) to Enter.
     * Nie jest to wybór, tylko fakt: terminal wysyła dla nich dokładnie ten sam
     * bajt, więc rozdzielić ich nie sposób bez rozszerzonego protokołu
     * klawiatury (poza zakresem — patrz plan kroku 19).
     */
    private function controlLetter(string $byte): ?string
    {
        $code = ord($byte);

        if ($code < 0x01 || $code > 0x1A) {
            return null;
        }

        return chr($code + 0x60);
    }

    private function utf8SequenceLength(int $firstByte): int
    {
        return match (true) {
            $firstByte < 0x80 => 1,
            ($firstByte & 0xE0) === 0xC0 => 2,
            ($firstByte & 0xF0) === 0xE0 => 3,
            ($firstByte & 0xF8) === 0xF0 => 4,
            // Osierocony bajt kontynuacji — konsumujemy pojedynczo, żeby nie zablokować bufora.
            default => 1,
        };
    }
}
