<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use LightManager\Module\Docker\Application\Port\LogReaderPort;

/**
 * Rozbieranie strumienia logów kontenera na wiersze (krok 51).
 *
 * **To jest jedna z dwóch pułapek nazwanych w planie kroku**, i nazwano ją tam
 * dlatego, że daje „działa, ale wygląda na zepsute”: logi kontenera **bez TTY
 * są multipleksowane**. Każdą porcję poprzedza osiem bajtów — numer strumienia
 * (1 = wyjście, 2 = błędy), trzy bajty wypełnienia i cztery bajty długości
 * w kolejności sieciowej. Czytane jak zwykły tekst dają śmieci co kilka wierszy:
 * pierwszy bajt ramki bywa niewidoczny, a czwarty ma wartość znaku sterującego.
 *
 * Kontener **z TTY** przysyła ten sam strumień **bez ramek**, bo pseudoterminal
 * scala wyjście z błędami po swojej stronie. Rozstrzygamy to **treścią, a nie
 * pytaniem do demona**: ramka zaczyna się bajtem 0, 1 albo 2, po którym idą trzy
 * zera. Zwykły tekst nie ma prawa tak wyglądać — trzy bajty zerowe pod rząd nie
 * padają w żadnym kodowaniu, którym pisze się logi. Pytanie o `Config.Tty`
 * kosztowałoby drugi obieg do demona na każdy otwarty log.
 *
 * Czytnik jest **stanowy i musi taki być**: porcja przychodzi z gniazda
 * w kawałkach dowolnej wielkości, więc ramka bywa przecięta w połowie nagłówka,
 * a wiersz — w połowie zdania. Wszystko, co niepełne, czeka do następnej porcji.
 */
final class LogFrameReader implements LogReaderPort
{
    /** Osiem bajtów: strumień, trzy wypełniające, cztery długości. */
    private const HEADER_BYTES = 8;

    /** Bufor bajtów, których jeszcze nie dało się rozebrać. */
    private string $pending = '';

    /** Wiersz w budowie — porcja skończyła się w jego środku. */
    private string $partialLine = '';

    /** `null` — jeszcze nie wiemy, czy strumień jest ramkowany. */
    private ?bool $framed = null;

    /**
     * Dokłada porcję i oddaje **wiersze, które się z niej domknęły**.
     *
     * @return list<string>
     */
    public function push(string $chunk): array
    {
        if ($chunk === '') {
            return [];
        }

        $this->pending .= $chunk;

        if ($this->framed === null) {
            // **Rozstrzygamy dopiero z ósmym bajtem**, a nie pierwszą porcją, jaka
            // przyjdzie. Porcja krótsza od nagłówka wygląda jak zwykły tekst
            // z prostego powodu — nie ma w niej czego sprawdzić — a odpowiedź raz
            // udzielona obowiązuje do końca strumienia. Rozstrzygnięcie podjęte
            // za wcześnie zamieniało cały log ramkowany w wiersze zaczynające się
            // ośmioma kropkami; złapał to test, a nie oko.
            if (strlen($this->pending) < self::HEADER_BYTES) {
                return [];
            }

            $this->framed = self::looksFramed($this->pending);
        }

        return $this->framed ? $this->readFramed() : $this->readPlain();
    }

    /**
     * Oddaje wiersz niedokończony i zapomina o nim — wołane, gdy strumień się
     * skończył.
     *
     * Kontener kończący pracę bez znaku nowej linii ma prawo do ostatniego
     * zdania: bez tego ostatni wiersz logu ginął, a to zwykle **ten
     * najważniejszy** — komunikat, po którym proces padł.
     */
    public function flush(): ?string
    {
        if ($this->framed === null && $this->pending !== '') {
            // Strumień skończył się, zanim uzbierało się osiem bajtów — czyli
            // zanim dało się rozstrzygnąć, czy jest ramkowany. Resztę czytamy
            // wtedy jak tekst, bo ramka krótsza od własnego nagłówka nie istnieje.
            $this->partialLine .= str_replace("\n", ' ', $this->pending);
            $this->pending = '';
        }

        $line = self::readable($this->partialLine);
        $this->partialLine = '';

        return $line === '' ? null : $line;
    }

    /**
     * Czy początek strumienia wygląda na ramkę multipleksera.
     *
     * Sprawdzamy trzy bajty wypełnienia, a nie sam numer strumienia: znak `\x01`
     * na początku tekstu jest niemożliwy w praktyce, ale trzy zera pod rząd są
     * niemożliwe **z definicji** kodowań, którymi pisze się logi.
     */
    private static function looksFramed(string $bytes): bool
    {
        if (strlen($bytes) < self::HEADER_BYTES) {
            return false;
        }

        return in_array($bytes[0], ["\x00", "\x01", "\x02"], true)
            && substr($bytes, 1, 3) === "\x00\x00\x00";
    }

    /**
     * Rozbiera tyle pełnych ramek, ile ich w buforze stoi.
     *
     * @return list<string>
     */
    private function readFramed(): array
    {
        $lines = [];

        while (strlen($this->pending) >= self::HEADER_BYTES) {
            $header = unpack('Cstream/C3padding/Nlength', substr($this->pending, 0, self::HEADER_BYTES));
            $length = is_array($header) ? $header['length'] ?? null : null;

            if (!is_int($length)) {
                // Nagłówka nie dało się rozczytać — bufor jest w stanie, którego
                // nie umiemy naprawić, więc kończymy czytanie tej porcji zamiast
                // zgadywać, gdzie zaczyna się następna ramka.
                return $lines;
            }

            if (strlen($this->pending) < self::HEADER_BYTES + $length) {
                // Ramka przecięta w połowie treści — czeka na następną porcję.
                return $lines;
            }

            $payload = substr($this->pending, self::HEADER_BYTES, $length);
            $this->pending = substr($this->pending, self::HEADER_BYTES + $length);

            foreach ($this->split($payload) as $line) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * Strumień bez ramek — cały bufor jest treścią.
     *
     * @return list<string>
     */
    private function readPlain(): array
    {
        $payload = $this->pending;
        $this->pending = '';

        return $this->split($payload);
    }

    /**
     * Tnie treść na wiersze, przechowując ostatni niedokończony.
     *
     * Znaki sterujące poza tabulatorem zamieniamy na kropkę: log bywa nośnikiem
     * sekwencji ANSI (kolorowe wypisy serwerów), a te wypisane w klatce
     * przestawiłyby renderer w stan, którego nikt nie zamawiał. Podgląd tekstu
     * w kroku 29 rozstrzygnął to tak samo i z tego samego powodu.
     *
     * @return list<string>
     */
    private function split(string $payload): array
    {
        $text = $this->partialLine . str_replace("\r\n", "\n", $payload);
        $parts = explode("\n", $text);
        $this->partialLine = (string) array_pop($parts);

        return array_map(self::readable(...), $parts);
    }

    /**
     * Wiersz nadający się do pokazania: bez znaków sterujących i w poprawnym
     * UTF-8.
     *
     * Wzorzec jest **bajtowy, bez modyfikatora `u`**, i to jest różnica, na
     * której łatwo się przejechać: log bywa strumieniem bajtów w kodowaniu, którego
     * nikt nie deklarował, a `preg_replace()` z `u` na treści niepoprawnej wraca
     * `null` — czyli cały wiersz zniknąłby, zamiast stracić jeden znak. Bajty
     * spoza UTF-8 podmieniamy dopiero potem i jawnie, bo `TextView` dostaje
     * wiersze **już zdekodowane** (reguła 11i).
     */
    private static function readable(string $line): string
    {
        $stripped = preg_replace('/[\x00-\x08\x0b-\x1f\x7f]/', '.', rtrim($line, "\r"));
        $text = $stripped ?? $line;

        return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
}
