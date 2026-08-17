<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

use LightManager\Application\Port\ClipboardPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Schowek przez `OSC 52` — implementacja wspólna dla toru sixelowego
 * i tekstowego (krok 57).
 *
 * Jedna usługa na dwa tory, bo oba rozmawiają z **tym samym** terminalem:
 * różnią się tym, czym rysują klatkę, a nie tym, przez co idzie sekwencja
 * sterująca. Droga jest przy tym jedyna, jaką ten krok zna, i wybrano ją nie
 * z wygody — `xclip`/`xsel` są na maszynie i **przegrały** (D95 nr 5), bo
 * potrzebują serwera okien, czyli nie działają ani przez SSH, ani w konsoli.
 * A to jest dokładnie ta sytuacja, w której menadżer plików w terminalu bywa
 * najczęściej.
 *
 * **Zakończenie łańcucha jest w postaci z normy** (`ST` = `ESC \`), nie `BEL`:
 * przyjmują je oba, a norma zna jedno. Przy **czytaniu** znamy obie postacie, bo
 * tam nie my wybieramy (patrz `KeySequenceParser`).
 *
 * **Cena tej drogi jest nazwana w D95 nr 5 i zamienia się tutaj na jedno
 * zdanie kodu, a nie na komentarz**: `requestText()` wolno zawołać wyłącznie
 * z obsługi polecenia użytkownika. Odblokowanie `GetSelection` w `bin/run.sh`
 * pozwala aplikacji działającej w terminalu **przeczytać cudzy schowek** — więc
 * nie ma tu ani jednego wywołania ze startu, z taktu ani z rysowania klatki,
 * a treść odpowiedzi ta klasa **nigdy nie widzi**: rozbiera ją parser wejścia,
 * a doręcza ten, kto ma ognisko.
 */
final class TerminalClipboardService extends AbstractSingleton implements ClipboardPort
{
    /** `OSC 52`, schowek systemowy (`c` = CLIPBOARD, nie PRIMARY). */
    private const SELECTION = 'c';

    private const OSC = "\e]52;";

    private const STRING_TERMINATOR = "\e\\";

    /**
     * Górna granica treści kładzionej w schowku, w bajtach **przed** zakodowaniem.
     *
     * Próg istnieje dlatego, że przekroczenie limitu łańcucha OSC kończy się
     * u terminala **milcząco, a nie błędem** — a kopiowanie oddające połowę
     * zawartości bez ani jednego słowa jest gorsze od kopiowania, które odmawia.
     * Terminal swojego limitu nie podaje i nie da się go zapytać, więc jedyne, co
     * można zrobić uczciwie, to postawić własny próg i **powiedzieć**, gdy treść
     * go przekracza.
     *
     * **Zmierzone pod XTermem 390** (`bin/terminal-probe`, klawisz `p`, próbka
     * podwajana; odczyt `xclip -o`): treść do **256 kB** dochodzi w całości,
     * a przy **512 kB schowek zostaje z poprzednią zawartością** — bez błędu, bez
     * sygnału, bez śladu. Próg jest zatem prawdziwy, tylko leży czterokrotnie
     * wyżej niż ta stała.
     *
     * **Zostajemy przy 64 kB i jest to wybór, nie oszczędność.** Zmierzony pułap
     * należy do **jednego** terminala na **jednej** maszynie; WezTerm, foot
     * i mlterm mają własne, a multiplekser w środku drogi bywa znacznie
     * skromniejszy. Wartość podniesiona do zmierzonego pułapu wiązałaby aplikację
     * z zachowaniem XTerma, a 64 kB starcza na każdą treść, którą da się obrysować
     * na ekranie albo zebrać z zaznaczonych nazw — i po zakodowaniu (base64 jest
     * o trzecią część dłuższy) daje ~87 kB sekwencji.
     */
    private const MAX_TEXT_BYTES = 65536;

    public function put(string $text): ?string
    {
        if ($text === '') {
            return 'clipboard.problem.empty';
        }

        if (strlen($text) > self::MAX_TEXT_BYTES) {
            return 'clipboard.problem.too-long';
        }

        $this->terminal()->write(self::OSC . self::SELECTION . ';' . base64_encode($text) . self::STRING_TERMINATOR);

        // Zapis nie ma potwierdzenia i mieć go nie może: `OSC 52` jest
        // jednostronny, a terminal, który operację ma zablokowaną, po prostu ją
        // pomija. Odpowiedź „udało się” znaczy tu więc „wysłano” — i to jest
        // wszystko, co ta droga wie. Sprawdzenie należy do człowieka
        // z drugą aplikacją, i tak stoi w kryteriach ukończenia kroku.
        return null;
    }

    public function requestText(): bool
    {
        $this->terminal()->write(self::OSC . self::SELECTION . ';?' . self::STRING_TERMINATOR);

        // `true` znaczy „zapytano”, nie „odpowiedzą”. Terminal bez obsługi
        // odczytu milczy — bez ani jednego bajtu, bez błędu, bez sygnału — więc
        // prośba ma po stronie rdzenia **termin**, a nie oczekiwanie.
        return true;
    }

    private function terminal(): TerminalService
    {
        return TerminalService::getInstance();
    }
}
