<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Treść pliku tekstowego w prostokącie: wiersze, zawijanie, numery i suwak.
 *
 * Komponent dostaje **gotowe wiersze**, a nie ścieżkę, i to jest cała jego
 * granica: nie czyta, nie dekoduje, nie zna pliku ani kodowania. Wszystko, co
 * ma z wejściem-wyjściem wspólnego, zostaje po stronie modułu — bo tam mieszka
 * wiedza o tym, co wolno przeczytać i jak długo (krok 29).
 *
 * **Wycinka też nie robi.** Plan kroku przewidywał parametr `offset`, jak
 * w `ListView`, ale rozstrzygnięcie użytkownika ze startu kroku postawiło odczyt
 * przesuwnym buforem „jak w edytorze”: moduł wczytuje wyłącznie te wiersze,
 * które właśnie pokazuje, więc nie ma czego wycinać. Lista przychodząca tutaj
 * **jest** widocznym wycinkiem, a nie całością, z której trzeba go wybrać.
 *
 * Dwie reguły rysowania i obie są rozstrzygnięciami, nie szczegółami:
 *
 * 1. **Zawijanie łamie po znaku, nie po słowie.** Podgląd kodu ma pokazywać
 *    wcięcia i strukturę wiersza, a nie akapit prozy — złamanie po słowie
 *    przesunęłoby każde wcięcie o przypadkową liczbę spacji.
 * 2. **Zawija się każdy wiersz dłuższy od kolumny treści — bez górnego progu.**
 *    Do poprawki z 2026-08-12 stał tu warunek odwrotny: wiersz dłuższy niż cały
 *    prostokąt (wiersze × kolumny) **nie zawijał się wcale**, tylko zostawał
 *    przycięty do jednej linijki. Uzasadniano to obawą, że zrzut JSON-a
 *    o milionie znaków zamieni panel w wiersz rozciągnięty na dwadzieścia pięć
 *    tysięcy linijek — obawa była nieprawdziwa, bo pętla rysująca kończy na
 *    wysokości prostokąta i tak. Skutek był za to prawdziwy i dokładnie
 *    odwrotny do zamierzonego: **jedyne wiersze, które nigdy się nie zawijały,
 *    to te najdłuższe** — czyli te, dla których zawijanie w ogóle istnieje.
 *    Plik o jednej długiej linii pokazywał jedną linijkę i pusty panel, a
 *    przełącznik zawijania nie robił przy nim nic widocznego.
 */
final class TextView implements ComponentInterface
{
    /** Poniżej tylu kolumn treści numery wierszy nie powstają — nie ma z czego. */
    private const MINIMUM_TEXT_COLUMNS = 8;

    /**
     * @param list<string>    $lines      wiersze **już** przygotowane do pokazania
     * @param bool            $wrap       czy wiersz dłuższy od prostokąta ma się zawijać
     * @param ?ScrollPosition $position   położenie okna; `null` — nie ma czego przewijać
     * @param ?int            $firstNumber numer pierwszego wiersza; `null` — bez numerów
     */
    public function __construct(
        private readonly array $lines,
        private readonly bool $wrap = true,
        private readonly ?ScrollPosition $position = null,
        private readonly ?int $firstNumber = null,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        // Suwakowi **oddajemy kolumnę**, a nie kładziemy go na treści, jak robi
        // to `ListView`. Różnica jest z rozmysłu: w liście plików pod szyną
        // wypada koniec nazwy, którą i tak widać z lewej, a w podglądzie tekstu
        // przykryty byłby znak kodu — czyli treść, po którą się tu przyszło.
        //
        // Kolumnę oddaje się **z chwilą podania położenia**, a nie z chwilą, gdy
        // suwak naprawdę się rysuje, i to jest poprawka z 2026-08-12. Szerokość
        // zależna od tego, czy akurat jest co przewijać, zmieniałaby zawijanie
        // w locie — a od tej samej szerokości zależy, czym jest „przewiń o jedną
        // linijkę”. Plik mieszczący się w oknie miałby ją inną niż ten sam plik
        // po dopisaniu wiersza.
        $reserved = $this->position !== null;
        $position = $reserved && $this->position->isNeeded() ? $this->position : null;
        $text = $reserved ? $bounds->columnsFrom(0, $bounds->columns - 1) : $bounds;
        $gutter = $this->gutterWidth($text->columns, $text->rows);
        $content = $text->columnsFrom($gutter, $text->columns - $gutter);

        if ($content->isEmpty()) {
            return [];
        }

        $primitives = $this->rows($text, $content, $gutter);

        if ($position !== null) {
            $primitives[] = new Scrollbar(new Rect($bounds->row, $bounds->right(), $bounds->rows, 1), $position);
        }

        return $primitives;
    }

    /**
     * Wiersze treści wraz z numerami — tyle, ile zmieści się w prostokącie.
     *
     * @return list<Primitive>
     */
    private function rows(Rect $text, Rect $content, int $gutter): array
    {
        $primitives = [];
        $row = 0;
        $number = $this->firstNumber;

        foreach ($this->lines as $line) {
            if ($row >= $content->rows) {
                break;
            }

            foreach ($this->pieces($line, $content->columns, $content->rows - $row) as $index => $piece) {
                if ($row >= $content->rows) {
                    break;
                }

                if ($gutter > 0 && $index === 0 && $number !== null) {
                    // Numer dosunięty do prawej i **tylko przy pierwszym kawałku**:
                    // wiersze dalsze są tym samym wierszem pliku, więc numeru nie
                    // dostają — tak samo rozstrzygają to edytory.
                    foreach ((new Label('', (string) $number, Role::Muted))
                        ->draw($text->line($row)->columnsFrom(0, $gutter - 1)) as $primitive) {
                        $primitives[] = $primitive;
                    }
                }

                foreach ((new Label($piece))->draw($content->line($row)) as $primitive) {
                    $primitives[] = $primitive;
                }

                ++$row;
            }

            if ($number !== null) {
                ++$number;
            }
        }

        return $primitives;
    }

    /**
     * Wiersz pliku pocięty na wiersze siatki.
     *
     * Wiersz pusty daje jeden kawałek pusty, a nie zero — inaczej pusta linijka
     * w kodzie znikałaby, a wraz z nią numeracja przestałaby się zgadzać
     * z plikiem.
     *
     * Wysokość prostokąta jest tu **sufitem liczby kawałków, a nie progiem
     * zawijania**, i na tej różnicy polegała poprawka z 2026-08-12. Kroić dalej
     * nie ma po co — rysowanie i tak kończy się na dolnej krawędzi — ale kroić
     * *w ogóle* trzeba, bo inaczej wiersz dłuższy od panelu zostawał w jednej
     * linijce, czyli tam, gdzie zawijanie jest najbardziej potrzebne.
     *
     * @param int $rows ile wierszy siatki zostało do dołu prostokąta
     *
     * @return list<string>
     */
    private function pieces(string $line, int $columns, int $rows): array
    {
        $length = mb_strlen($line);

        if (!$this->wrap || $length <= $columns) {
            return [$line];
        }

        $pieces = [];

        for ($offset = 0; $offset < $length && count($pieces) < $rows; $offset += $columns) {
            $pieces[] = mb_substr($line, $offset, $columns);
        }

        return $pieces;
    }

    /**
     * Ile kolumn zostaje na **treść** po oddaniu miejsca suwakowi i numerom.
     *
     * Wystawione, bo pyta o to ten, kto czyta plik, i pyta **zanim** powstanie
     * komponent: od poprawki z 2026-08-12 przewijanie liczy się w linijkach
     * panelu, a linijka jest tym, co się mieści w tej właśnie szerokości.
     * Gdyby czytający liczył ją po swojemu, przewinięcie „o jedną linijkę”
     * rozjeżdżałoby się z tym, co widać — o tyle znaków, ile zabiera kolumna
     * numerów.
     *
     * @param int $lines ile wierszy pliku okno pokazuje — stąd bierze się
     *                   szerokość numeru największego z nich
     */
    public static function contentColumns(Rect $bounds, bool $scrolls, ?int $firstNumber, int $lines): int
    {
        $columns = $bounds->columns - ($scrolls ? 1 : 0);

        return max(0, $columns - self::gutterFor($columns, $firstNumber, $lines));
    }

    /**
     * Szerokość kolumny numerów wraz z odstępem; `0` — numerów nie ma.
     *
     * Numery ustępują treści, a nie odwrotnie: gdy po ich odjęciu zostałoby na
     * tekst mniej niż kilka znaków, kolumna nie powstaje w ogóle. To ta sama
     * reguła, którą krok 27 nazwał ustępowaniem — tyle że rozstrzygnięta tutaj,
     * bo dwie kolumny to za mało, żeby wołać po nie `Distribution`.
     */
    private function gutterWidth(int $columns, int $rows): int
    {
        return self::gutterFor($columns, $this->firstNumber, $rows);
    }

    /**
     * Szerokość liczona z **wysokości prostokąta**, a nie z liczby wczytanych
     * wierszy, i to jest poprawka z 2026-08-12.
     *
     * Liczba wierszy w oknie zmienia się przy każdym przewinięciu — plik o jednej
     * długiej linii ma ich jeden, a plik kodu tyle, ile linijek. Kolumna numerów
     * liczona z niej zmieniałaby więc szerokość w trakcie przewijania, a wraz
     * z nią miejsce zawinięć: obraz „pełzłby” w bok. Wysokość prostokąta jest
     * sufitem liczby wierszy i **nie zmienia się między klatkami**, więc obie
     * strony — czytająca plik i rysująca go — liczą z niej to samo.
     */
    private static function gutterFor(int $columns, ?int $firstNumber, int $rows): int
    {
        if ($firstNumber === null || $rows < 1) {
            return 0;
        }

        $width = mb_strlen((string) max(1, $firstNumber + $rows - 1)) + 1;

        return $columns - $width < self::MINIMUM_TEXT_COLUMNS ? 0 : $width;
    }
}
