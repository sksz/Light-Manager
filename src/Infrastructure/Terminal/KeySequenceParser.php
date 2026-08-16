<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerButton;
use LightManager\Application\Dto\PointerEvent;

/**
 * Zamienia surowe bajty ze STDIN na pojedyncze zdarzenia wejściowe.
 *
 * Klasa jest czysta (bez I/O i bez stanu) — cała wiedza o sekwencjach escape
 * siedzi tutaj, dzięki czemu daje się testować bez terminala.
 *
 * Od kroku 55 zdarzeniem bywa też **wskaźnik**: w trybie SGR (`\e[?1006h`)
 * terminal wysyła kliknięcia tą samą sekwencją CSI, którą wysyła strzałki,
 * różniącą się prywatnym bajtem `<` zaraz po `ESC [`. Rozbiór jest przez to
 * jeden, a wynik ma dwie postacie — stąd `ParsedKey` niosący `InputEvent`.
 */
final class KeySequenceParser
{
    private const ESCAPE = "\e";

    /** Prywatny bajt otwierający sekwencję wskaźnika w trybie SGR. */
    private const SGR_POINTER_MARKER = '<';

    /**
     * Bity pierwszego parametru sekwencji SGR.
     *
     * Kodowanie XTerma: dwa najniższe bity to numer przycisku, wyżej stoją
     * modyfikatory, znacznik ruchu i kółko. `WHEEL` **zastępuje** numer
     * przycisku — obrót niczego nie naciska — a `EXTRA_BUTTONS` oznacza
     * przyciski boczne, których słownik nie zna (reguła 13).
     */
    private const SGR_BUTTON_MASK = 0b11;

    private const SGR_SHIFT = 4;

    private const SGR_ALT = 8;

    private const SGR_CTRL = 16;

    private const SGR_MOTION = 32;

    private const SGR_WHEEL = 64;

    private const SGR_EXTRA_BUTTONS = 128;

    /**
     * Bajt kończący sekwencję CSI/SS3 → klawisz.
     *
     * `P`–`S` to F1–F4 w postaci SS3 (`ESC O P`), którą wysyła XTerm w trybie
     * domyślnym. Ta sama tablica obsługuje wariant z modyfikatorem
     * (`ESC [ 1 ; 2 P` = Shift+F1) — od kroku 44 parametr modyfikatora nie
     * jest już odrzucany w całości: `Shift` wraca znacznikiem (`hasShift()`),
     * a klawisz bazowy zostaje ten sam.
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
            default => $this->parseAltCharacter($buffer),
        };
    }

    /**
     * `ESC` + znak drukowalny ASCII = `Alt`+znak (krok 29).
     *
     * Do kroku 29 ta gałąź oddawała samotny `Escape`, a następny bajt czekał na
     * kolejne wywołanie. Zmiana ma cenę i trzeba ją nazwać wprost:
     * **`Esc` naciśnięty tuż przed literą jest nierozróżnialny od `Alt`+litery**,
     * bo terminal wysyła w obu wypadkach dokładnie te same dwa bajty. Rozstrzyga
     * o tym wyłącznie czas — a że pętla czyta STDIN raz na takt, oba naciśnięcia
     * w jednym takcie trafiają do bufora razem. Tak samo rozstrzygają to
     * emulatory terminala i edytory od czasów VT100; rozdzielić je umie dopiero
     * rozszerzony protokół klawiatury (poza zakresem — jak w kroku 19).
     *
     * Bajt niedrukowalny po `ESC` — w tym drugi `ESC` — zostaje przy dawnej
     * odpowiedzi: samotny `Escape`, reszta przy następnym wywołaniu. `Alt`
     * ze znakiem spoza ASCII nie powstaje, bo nie ma go kto nacisnąć: skróty
     * aplikacji wiszą na literach.
     */
    private function parseAltCharacter(string $buffer): ParsedKey
    {
        $code = ord($buffer[1]);

        if ($code < 0x20 || $code > 0x7E) {
            return $this->loneEscape();
        }

        return new ParsedKey(KeyPress::alt($buffer[1]), 2);
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

        if ($length > 2 && $buffer[2] === self::SGR_POINTER_MARKER) {
            return $this->parsePointerSequence($buffer, $mayGrow);
        }

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

        if ($this->hasShift($parameters)) {
            return new ParsedKey(KeyPress::shifted($key, $raw), $consumed);
        }

        return new ParsedKey(KeyPress::special($key, $raw), $consumed);
    }

    /**
     * Wskaźnik w trybie SGR: `ESC [ < b ; x ; y M` (naciśnięcie) i `… m`
     * (zwolnienie).
     *
     * Tryb SGR jest **obowiązkowy, a nie dodatkowy**, i stąd bierze się jedyna
     * postać wskaźnika, jaką ten parser zna: kodowanie domyślne (`1000` bez
     * `1006`) zapisuje współrzędną jako bajt z przesunięciem 32, więc powyżej
     * 223. kolumny przestaje działać w ogóle. Okno pomiarowe projektu ma 100
     * kolumn tylko dlatego, że tak je ustawiono w `bin/run.sh`.
     *
     * Współrzędne przychodzą liczone **od jedynki** i odejmuje się ją tutaj —
     * to jest całe przeliczenie, którego wymaga rozstrzygnięcie „współrzędne
     * w komórkach, nigdy w pikselach”. Dalej, aż po ekran, chodzi już siatka
     * znakowa `Rect`a.
     */
    private function parsePointerSequence(string $buffer, bool $mayGrow): ?ParsedKey
    {
        $matches = [];

        if (preg_match('/^\e\[<(\d+);(\d+);(\d+)([Mm])/', $buffer, $matches) !== 1) {
            // Bajty pasujące do początku sekwencji, ale jeszcze bez końca —
            // czekamy na resztę tą samą drogą, którą czeka strzałka. Śmieć,
            // który nigdy się nie domknie, rozstrzyga się po upływie okna.
            if ($mayGrow && preg_match('/^\e\[<[\d;]*$/', $buffer) === 1) {
                return null;
            }

            return $this->loneEscape();
        }

        $flags = (int) $matches[1];
        $column = (int) $matches[2] - 1;
        $row = (int) $matches[3] - 1;
        $released = $matches[4] === 'm';
        $consumed = strlen($matches[0]);

        $action = $this->pointerAction($flags, $released);

        if ($action === null) {
            // Kółko poziome i przyciski boczne: sekwencja jest poprawna, więc
            // trzeba ją **zjeść**, a nie zostawić w buforze — ale odbiorcy nie
            // ma (reguła 13), więc zdarzenia z niej nie powstaje.
            return new ParsedKey(KeyPress::special(Key::Unknown, $matches[0]), $consumed);
        }

        return new ParsedKey(
            new PointerEvent(
                max(0, $row),
                max(0, $column),
                $this->pointerButton($flags),
                $action,
                ($flags & self::SGR_CTRL) !== 0,
                ($flags & self::SGR_ALT) !== 0,
                ($flags & self::SGR_SHIFT) !== 0,
            ),
            $consumed,
        );
    }

    /**
     * Rodzaj czynności wskaźnika; `null` znaczy „nie mamy na to pozycji
     * w słowniku”.
     *
     * Kolejność pytań ma znaczenie: kółko **zastępuje** przycisk, więc pyta się
     * o nie pierwsze; ruch rozpoznaje się dopiero potem, bo bit ruchu bywa
     * ustawiony razem z numerem przycisku trzymanego w trakcie przeciągania.
     */
    private function pointerAction(int $flags, bool $released): ?PointerAction
    {
        if (($flags & self::SGR_WHEEL) !== 0) {
            return match ($flags & self::SGR_BUTTON_MASK) {
                0 => PointerAction::ScrollUp,
                1 => PointerAction::ScrollDown,
                // Kółko poziome — nie ma odbiorcy.
                default => null,
            };
        }

        if (($flags & self::SGR_EXTRA_BUTTONS) !== 0) {
            return null;
        }

        if (($flags & self::SGR_MOTION) !== 0) {
            return PointerAction::Drag;
        }

        return $released ? PointerAction::Release : PointerAction::Press;
    }

    /**
     * Przycisk z dwóch najniższych bitów — **z wyjątkiem kółka**.
     *
     * Przy obrocie kółka te same bity niosą **kierunek**, a nie przycisk, więc
     * czytane wprost dawały `Middle` przy każdym obrocie w dół (bit 0 zapalony
     * w wartości 65). Skutek był cichy i rozjeżdżał tory: to samo pokręcenie
     * kółkiem oddawało w terminalu `Middle`, a w oknie `Left`, bo
     * `GlfwPointerMapper::mapScroll()` przycisku z kierunku nie wyprowadza.
     * Obrót niczego nie naciska, więc odpowiedzią jest `Left` — wartość
     * obojętna, tak samo jak w torze okienkowym.
     */
    private function pointerButton(int $flags): PointerButton
    {
        if (($flags & self::SGR_WHEEL) !== 0) {
            return PointerButton::Left;
        }

        return match ($flags & self::SGR_BUTTON_MASK) {
            1 => PointerButton::Middle,
            2 => PointerButton::Right,
            default => PointerButton::Left,
        };
    }

    private function isParameterByte(string $byte): bool
    {
        return ($byte >= '0' && $byte <= '9') || $byte === ';' || $byte === '?';
    }

    /**
     * Modyfikatory (`ESC [ 3 ; 5 ~` = Ctrl+Delete) nie zmieniają klawisza
     * bazowego — z jednym wyjątkiem od kroku 44: **`Shift` jest czytany**
     * i wraca znacznikiem w słowniku. `Ctrl` i `Alt` przy klawiszach nazwanych
     * pozostają odrzucane, bo nie mają w aplikacji ani jednego użytkownika,
     * a para znaczników bez odbiorcy to dokładnie ten dług, przed którym
     * przestrzega docblock `KeyPress::alt()`.
     */
    private function firstParameter(string $parameters): string
    {
        $separator = strpos($parameters, ';');

        return $separator === false ? $parameters : substr($parameters, 0, $separator);
    }

    /**
     * Czy drugi parametr sekwencji niesie `Shift`.
     *
     * Kodowanie XTerma: parametr modyfikatora to `1 + maska`, gdzie bit 1 znaczy
     * `Shift`, 2 — `Alt`, 4 — `Ctrl`. Stąd `ESC [ 3 ; 2 ~` to `Shift`+`Delete`,
     * a `ESC [ 1 ; 2 A` — `Shift`+strzałka w górę. Wartość `0` i `1` znaczą
     * „bez modyfikatorów”; brak drugiego parametru — tym bardziej.
     */
    private function hasShift(string $parameters): bool
    {
        $separator = strpos($parameters, ';');

        if ($separator === false) {
            return false;
        }

        $modifier = (int) substr($parameters, $separator + 1);

        return $modifier >= 2 && (($modifier - 1) & 1) === 1;
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
