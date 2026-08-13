<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

/**
 * Kawałek napisu wskazany przesunięciem i długością — **w znakach, nie w bajtach**.
 *
 * Rozstrzygnięcie ze startu kroku 30, wymagane planem przed pierwszą linią kodu:
 * przesunięcie liczy się w znakach, bo rysuje się je w kolumnach, a kolumna
 * odpowiada znakowi, nie bajtowi. Nazwa `zażółć.txt` ma dziewięć znaków
 * i trzynaście bajtów; dopasowanie liczone bajtami wylądowałoby o cztery kolumny
 * za daleko i w połowie znaku.
 *
 * Wiersz niesie **zakresy**, a nie gotowy podział na kawałki, bo tylko komponent
 * wie, w której kolumnie stoi napis po rozdziale szerokości z kroku 27 — i tylko
 * on wie, ile z napisu zostało po przycięciu.
 */
final readonly class TextSpan
{
    public function __construct(
        /** Przesunięcie początku, w znakach od początku napisu. */
        public int $offset,
        /** Długość, w znakach. */
        public int $length,
    ) {
    }

    /**
     * Wszystkie wystąpienia fragmentu w napisie, bez rozróżniania wielkości liter.
     *
     * Szukanie idzie `mb_stripos()`, więc składanie wielkości liter obejmuje
     * alfabety spoza ASCII — `Ł` znajduje `ł`, a nie tylko `L` znajduje `l`.
     * Wystąpienia **nie zachodzą na siebie**: `aa` w `aaaa` daje dwa dopasowania,
     * nie trzy, bo szukanie rusza za końcem poprzedniego.
     *
     * @return list<self>
     */
    public static function occurrencesOf(string $needle, string $haystack): array
    {
        if ($needle === '' || $haystack === '') {
            return [];
        }

        $length = mb_strlen($needle);
        $spans = [];
        $from = 0;

        while (($at = mb_stripos($haystack, $needle, $from)) !== false) {
            $spans[] = new self($at, $length);
            $from = $at + $length;
        }

        return $spans;
    }

    /**
     * Ten sam zakres przycięty do napisu o podanej długości; `null`, gdy nie
     * został z niego ani jeden znak.
     *
     * Potrzebne, bo komórka tabeli bywa krótsza od treści: nazwa przycięta
     * wielokropkiem gubi koniec, a wraz z nim dopasowania, które w nim leżały.
     * Podświetlenie sięgające za wielokropek malowałoby tło na znaku, którego
     * w treści nie ma.
     */
    public function clippedTo(int $length): ?self
    {
        if ($this->offset >= $length || $this->length < 1) {
            return null;
        }

        return new self($this->offset, min($this->length, $length - $this->offset));
    }

    public function equals(self $other): bool
    {
        return $this->offset === $other->offset && $this->length === $other->length;
    }
}
