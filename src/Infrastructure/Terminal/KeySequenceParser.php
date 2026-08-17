<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

use LightManager\Application\Dto\ClipboardText;
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
 *
 * Od kroku 57 dochodzi **trzecia gałąź i trzecia postać zdarzenia**: odpowiedź
 * terminala na pytanie o schowek (`OSC 52`). Różni się od dwóch poprzednich
 * dwiema rzeczami naraz i obie trzeba znać:
 *
 * 1. **Nie jest sekwencją CSI**, więc nie wchodzi przez `ESC [`, tylko przez
 *    `ESC ]` — a ta droga była do kroku 57 zajęta: `]` to bajt drukowalny, więc
 *    `parseAltCharacter()` z kroku 29 rozbierał ją jako `Alt`+`]`. Nowa gałąź
 *    stoi przez to **przed** tamtą.
 * 2. **Jest długa i przychodzi kawałkami.** Strzałka ma trzy bajty i mieści się
 *    w jednym odczycie; schowek ma tyle, ile ma zawartość, więc `parse()`
 *    i `parseAfterTimeout()` odpowiadają na nią **tak samo**: „czekaj dalej”.
 *    To jest jedyne miejsce, w którym `parseAfterTimeout()` nie rozstrzyga —
 *    i dlatego wolno tak zrobić tylko wtedy, gdy w buforze stoi **pełny
 *    znacznik** `ESC ] 5 2 ;`. Bez tego warunku samo naciśnięcie `Alt`+`]`
 *    zamurowałoby wejście w oczekiwaniu na zakończenie łańcucha, którego nikt
 *    nie wysłał.
 */
final class KeySequenceParser
{
    private const ESCAPE = "\e";

    /** Znacznik odpowiedzi o schowku: `OSC` (`ESC ]`), numer operacji, średnik. */
    private const CLIPBOARD_MARKER = "\e]52;";

    /** Zakończenie łańcucha OSC w postaci z normy (`ST` = `ESC \`). */
    private const STRING_TERMINATOR = "\e\\";

    /** Zakończenie w postaci starszej — terminale wysyłają obie, więc znamy obie. */
    private const BELL = "\a";

    /**
     * Górna granica niedokończonej odpowiedzi o schowku.
     *
     * Bez niej sekwencja, która nigdy się nie domknie — bo terminal padł
     * w połowie zapisu albo bo ktoś wkleił do terminala bajty wyglądające jak
     * początek odpowiedzi — zatrzymywałaby **całe** wejście aplikacji na zawsze.
     * Wartość jest hojna wobec tego, po co ten mechanizm istnieje (schowek to
     * zwykle wiersz, czasem plik konfiguracyjny) i skromna wobec pamięci.
     */
    private const MAX_PENDING_CLIPBOARD_BYTES = 1048576;

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
     *
     * **Jeden wyjątek od słowa „rozstrzygająca” dokłada krok 57**: rozpoczęta
     * odpowiedź o schowku nadal oddaje `null`, bo jej długość zależy od
     * zawartości schowka, a nie od protokołu — czekanie kończy dopiero
     * zakończenie łańcucha albo przekroczenie progu. Warunkiem jest **pełny
     * znacznik** w buforze, więc `Alt`+`]` rozstrzyga się tutaj tak, jak
     * rozstrzygał od kroku 29.
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
            // Gałąź OSC stoi **przed** `Alt`+znakiem, bo `]` jest znakiem
            // drukowalnym i tamta gałąź złapałaby ją pierwsza (krok 57).
            ']' => $this->parseOperatingSystemCommand($buffer, $mayGrow),
            default => $this->parseAltCharacter($buffer),
        };
    }

    /**
     * Łańcuch OSC — dziś w jednej jedynej postaci: odpowiedź o schowku
     * (`ESC ] 52 ; <wybór> ; <base64> ST`).
     *
     * Trzy rozstrzygnięcia zapisane w tym rachunku:
     *
     * - **Oba zakończenia są równoprawne** (`ST` i `BEL`). Norma zna pierwsze,
     *   terminale wysyłają oba, a zgadywanie, który przyjdzie, nie ma jak się
     *   udać: liczy się to, które przyszło **wcześniej**, bo drugie może stać
     *   w środku treści.
     * - **Czekamy tylko na to, co się zapowiedziało.** Dopóki w buforze nie ma
     *   pełnego `ESC ] 52 ;`, sekwencja rozstrzyga się starą drogą — czyli
     *   `Alt`+`]`. Inaczej jedno naciśnięcie klawisza zatrzymywałoby wejście.
     * - **Nierozczytany ładunek nie jest pustym schowkiem.** Zjadamy sekwencję
     *   (bo jest domknięta i nie ma po co zostawiać jej w buforze), ale zdarzenia
     *   z niej nie powstaje: prośba wygaśnie po terminie i użytkownik usłyszy, że
     *   schowek jest nieosiągalny. Pusta odpowiedź (`ESC ] 52 ; c ; ST`) jest za
     *   to prawdziwie pustym schowkiem i wraca jako `ClipboardText('')`.
     */
    private function parseOperatingSystemCommand(string $buffer, bool $mayGrow): ?ParsedKey
    {
        if (!str_starts_with($buffer, self::CLIPBOARD_MARKER)) {
            // Znacznik jeszcze niepełny, ale bufor nadal może się nim stać —
            // czekamy tą samą drogą, którą czeka strzałka.
            if ($mayGrow && str_starts_with(self::CLIPBOARD_MARKER, $buffer)) {
                return null;
            }

            return $this->parseAltCharacter($buffer);
        }

        $end = $this->stringTerminatorAt($buffer);

        if ($end === null) {
            if (strlen($buffer) <= self::MAX_PENDING_CLIPBOARD_BYTES) {
                // Jedyne miejsce, w którym `parseAfterTimeout()` nie rozstrzyga:
                // odpowiedź o schowku przychodzi kawałkami przez kilka taktów.
                return null;
            }

            // Próg przekroczony — sekwencja nie domknie się już nigdy. Zjadamy
            // ją w całości, żeby wejście odżyło; o milczeniu schowka powie
            // wygaśnięcie prośby.
            return new ParsedKey(KeyPress::special(Key::Unknown, $buffer), strlen($buffer));
        }

        [$offset, $length] = $end;
        $consumed = $offset + $length;
        $body = substr($buffer, strlen(self::CLIPBOARD_MARKER), $offset - strlen(self::CLIPBOARD_MARKER));
        $payload = base64_decode($this->base64Of($body), true);

        if ($payload === false) {
            return new ParsedKey(KeyPress::special(Key::Unknown, substr($buffer, 0, $consumed)), $consumed);
        }

        return new ParsedKey(new ClipboardText($payload), $consumed);
    }

    /**
     * Gdzie kończy się łańcuch OSC i ile bajtów zajmuje zakończenie — albo
     * `null`, gdy jeszcze się nie skończył.
     *
     * @return ?array{int, int}
     */
    private function stringTerminatorAt(string $buffer): ?array
    {
        $st = strpos($buffer, self::STRING_TERMINATOR, strlen(self::CLIPBOARD_MARKER));
        $bell = strpos($buffer, self::BELL, strlen(self::CLIPBOARD_MARKER));

        return match (true) {
            $st !== false && ($bell === false || $st < $bell) => [$st, strlen(self::STRING_TERMINATOR)],
            $bell !== false => [$bell, strlen(self::BELL)],
            default => null,
        };
    }

    /**
     * Sam ładunek base64 z treści łańcucha — bez pola wyboru schowka.
     *
     * Pole bywa puste (`ESC ] 52 ; ; …`) i bywa echem tego, o co pytaliśmy
     * (`c`), więc czyta się je jako „wszystko do pierwszego średnika”, a nie
     * jako znany zestaw liter. Ładunek bez średnika przed sobą jest odpowiedzią
     * terminala, który pola nie odesłał — i taką odpowiedź też przyjmujemy,
     * bo alternatywą byłoby odrzucenie schowka z powodu formalności.
     */
    private function base64Of(string $body): string
    {
        $separator = strpos($body, ';');

        return $separator === false ? $body : substr($body, $separator + 1);
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
