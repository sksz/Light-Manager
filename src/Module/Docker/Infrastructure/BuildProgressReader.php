<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use LightManager\Module\Docker\Application\BuildMessage;
use LightManager\Module\Docker\Application\Port\BuildReaderPort;

/**
 * Rozbieranie strumienia postępu budowy (krok 51).
 *
 * **Druga z dwóch pułapek nazwanych w planie kroku.** Budowa nie oddaje ani
 * tekstu, ani jednego JSON-a: oddaje **strumień obiektów JSON, po jednym na
 * porcję**, wymieszanych z komunikatami błędów — i robi to w tempie, w jakim
 * pracuje. Czytane jak tekst dają wiersze `{"stream":"Step 1/4 : FROM alpine\n"}`
 * pokazywane użytkownikowi wprost; czytane jak jeden dokument nie dają nic, bo
 * dokument nie ma korzenia.
 *
 * Obiekty, które nas obchodzą, są cztery:
 *
 * - `stream` — wiersz wypisu budowy, ten sam, który widać w terminalu;
 * - `status` z `progress` — pobieranie warstwy obrazu bazowego;
 * - `error` z `errorDetail` — budowa się nie udała **i to jest jedyne miejsce,
 *   w którym się o tym dowiemy**: odpowiedź HTTP ma wtedy kod 200, bo z punktu
 *   widzenia protokołu wszystko poszło dobrze;
 * - `aux.ID` — skrót zbudowanego obrazu, czyli to, po co ta budowa była.
 *
 * Czytnik jest **stanowy**, jak czytnik logów, i z tego samego powodu: obiekt
 * bywa przecięty w połowie między jedną porcją a drugą.
 */
final class BuildProgressReader implements BuildReaderPort
{
    /** Bajty, które nie domknęły się w wiersz. */
    private string $partial = '';

    /**
     * Dokłada porcję i oddaje **komunikaty, które się z niej domknęły**.
     *
     * @return list<BuildMessage>
     */
    public function push(string $chunk): array
    {
        if ($chunk === '') {
            return [];
        }

        $text = $this->partial . $chunk;
        $lines = explode("\n", $text);
        $this->partial = (string) array_pop($lines);

        $messages = [];

        foreach ($lines as $line) {
            $message = self::interpret(trim($line));

            if ($message !== null) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    /**
     * Jeden obiekt JSON zamieniony na komunikat — albo `null`, gdy nie niesie
     * niczego, co użytkownik chciałby zobaczyć.
     *
     * Wiersza, którego nie da się rozczytać, **nie pokazujemy jako błąd**: demon
     * ma prawo dołożyć pole, którego dziś nie znamy, a „nieznany komunikat
     * budowy” w połowie udanej budowy wyglądałby jak awaria aplikacji.
     */
    private static function interpret(string $line): ?BuildMessage
    {
        if ($line === '') {
            return null;
        }

        $decoded = json_decode($line, true);

        if (!is_array($decoded)) {
            return null;
        }

        $error = $decoded['error'] ?? null;

        if (is_string($error) && $error !== '') {
            return BuildMessage::failure(self::readable($error));
        }

        $aux = $decoded['aux'] ?? null;

        if (is_array($aux) && is_string($aux['ID'] ?? null) && $aux['ID'] !== '') {
            return BuildMessage::built($aux['ID']);
        }

        $stream = $decoded['stream'] ?? null;

        if (is_string($stream) && trim($stream) !== '') {
            return BuildMessage::step(self::readable(trim($stream)));
        }

        $status = $decoded['status'] ?? null;

        if (is_string($status) && $status !== '') {
            return BuildMessage::step(self::readable($status));
        }

        return null;
    }

    /** Bez znaków sterujących — budowa wypisuje sekwencje ANSI, gdy tylko może. */
    private static function readable(string $text): string
    {
        $stripped = preg_replace('/[\x00-\x08\x0b-\x1f\x7f]/', ' ', $text);

        return trim($stripped ?? $text);
    }
}
