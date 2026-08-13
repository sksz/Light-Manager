<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Dto;

use LightManager\Domain\ValueObject\ScrollPosition;

/**
 * Wycinek pliku widoczny w panelu podglądu — wiersze i to, gdzie się zaczynają.
 *
 * Okno niesie **dwie kotwice**: tę, od której się zaczyna, i tę, od której
 * zacznie się następne. Druga jest tu po to, żeby przewinięcie w dół nie musiało
 * niczego szukać — kolejny odczyt zaczyna się dokładnie tam, gdzie skończył się
 * poprzedni. Wstecz tak łatwo nie jest i dlatego ma osobną metodę w porcie:
 * początku poprzedniego wiersza nie da się odgadnąć bez zajrzenia do pliku.
 *
 * **Suwak liczy się w bajtach, nie w wierszach**, i to jest jedyne uczciwe
 * wyjście: liczby wierszy pliku nie znamy i poznać jej nie chcemy — kosztowałaby
 * przejście przez cały plik przy pierwszym pokazaniu podglądu. Bajty wiedzą
 * o pliku dokładnie tyle, ile trzeba, żeby powiedzieć „jesteś mniej więcej
 * w jednej trzeciej”, a to jest cała treść suwaka.
 */
final class TextWindow
{
    /**
     * @param list<string> $lines wiersze gotowe do pokazania
     * @param list<int>    $starts bajt, na którym zaczyna się każdy z nich
     * @param ?string      $problemKey klucz katalogu z powodem, dla którego wierszy nie ma
     */
    private function __construct(
        public readonly array $lines,
        public readonly array $starts,
        public readonly TextAnchor $anchor,
        public readonly TextAnchor $next,
        public readonly int $fileBytes,
        public readonly ?string $problemKey = null,
    ) {
    }

    /**
     * @param list<string> $lines
     * @param list<int>    $starts
     */
    public static function of(
        array $lines,
        array $starts,
        TextAnchor $anchor,
        TextAnchor $next,
        int $fileBytes,
    ): self {
        return new self($lines, $starts, $anchor, $next, $fileBytes);
    }

    /**
     * Kotwica na początku wiersza o podanym numerze w oknie.
     *
     * Potrzebna przewijaniu liczonemu w **linijkach panelu**: wiersz pliku
     * zajmuje po zawinięciu kilka linijek, więc przewinięcie o linijkę potrafi
     * zatrzymać się w środku wiersza — a wtedy kotwica ma stanąć na początku
     * **tego** wiersza, nie następnego. Bez bajtowych początków wierszy trzeba by
     * ich szukać osobnym odczytem, i to przy każdym naciśnięciu strzałki.
     */
    public function anchorOf(int $line): ?TextAnchor
    {
        $start = $this->starts[$line] ?? null;

        return $start === null ? null : new TextAnchor($start, $this->anchor->line + $line);
    }

    /**
     * Plik, którego nie pokażemy, wraz z powodem.
     *
     * Powód jest tu z tego samego przekonania, co przy sumie kontrolnej
     * (krok 25): milczące nierozpoczęcie pracy jest najgorszą z odpowiedzi.
     */
    public static function problem(string $key): self
    {
        return new self([], [], new TextAnchor(), new TextAnchor(), 0, $key);
    }

    /** Położenie okna na pliku; `null` — cały plik widać naraz. */
    public function scroll(): ?ScrollPosition
    {
        if ($this->fileBytes <= 0) {
            return null;
        }

        $first = max(0, min($this->anchor->byte, $this->fileBytes));
        $visible = max(0, min($this->next->byte, $this->fileBytes) - $first);

        return new ScrollPosition($first, $visible, $this->fileBytes);
    }
}
