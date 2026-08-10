<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Zbiór próbek jednej wielkości sprowadzony do czterech liczb.
 *
 * Mediana, nie średnia — pojedynczy przebieg, w który wszedł inny proces,
 * przesuwa średnią i zostaje niewidoczny. Minimum i maksimum jadą razem z
 * medianą, bo dopiero one mówią, czy liczbę wolno traktować poważnie: krok 13
 * dostawał dla tej samej konfiguracji 184–254 ms i nie miał tego jak zauważyć.
 *
 * Klasa jest czysta — żadnego I/O, żadnego Imagicka — więc reguła „kiedy pomiar
 * jest niewiarygodny” daje się sprawdzić testem jednostkowym, a nie tylko
 * obejrzeć na wydruku.
 */
final class Measurement
{
    /**
     * Powyżej tego ilorazu `max/min` wynik idzie na wydruk z ostrzeżeniem i nie
     * ma prawa trafić do wzorca jako fakt. Próg dobrany tak, by rozrzut
     * zaobserwowany w kroku 13 (254/184 ≈ 1,38) wpadał w ostrzeżenie.
     */
    public const UNSTABLE_SPREAD_RATIO = 1.35;

    /**
     * Poniżej tego rozrzutu **bezwzględnego** pomiar nie jest znakowany, choćby
     * iloraz był wysoki.
     *
     * Sam iloraz wystarczał, dopóki klatka trwała setki milisekund. Po
     * optymalizacji z kroku 17 scenariusze schodzą do kilku milisekund, a tam
     * zwykłe drgnięcie planisty (2 ms na 7) przekracza próg 1,35× — i cały
     * przebieg lądował jako niewiarygodny, mimo że różnica nie mogła wpłynąć na
     * żadną decyzję. Rozrzut mniejszy niż to, co wnosi samo środowisko, nie jest
     * informacją o kodzie.
     */
    public const UNSTABLE_SPREAD_MILLISECONDS = 3.0;

    private function __construct(
        public readonly float $median,
        public readonly float $minimum,
        public readonly float $maximum,
        public readonly int $count,
    ) {
    }

    /**
     * @param list<float> $samples
     *
     * @throws DiagnosticsException gdy nie ma czego uśredniać — pusty zbiór
     *                              znaczy, że pomiar w ogóle się nie odbył, a
     *                              zero byłoby wtedy kłamstwem
     */
    public static function fromSamples(array $samples): self
    {
        if ($samples === []) {
            throw DiagnosticsException::forEmptySampleSet();
        }

        sort($samples);

        return new self(
            self::medianOfSorted($samples),
            $samples[0],
            $samples[count($samples) - 1],
            count($samples),
        );
    }

    /** @param list<int> $samples */
    public static function fromIntegerSamples(array $samples): self
    {
        return self::fromSamples(array_map(static fn (int $sample): float => (float) $sample, $samples));
    }

    /**
     * Rozrzut jako iloraz skrajnych próbek. Pomiar krótszy niż rozdzielczość
     * zegara daje minimum równe zeru — wtedy iloraz nie znaczy nic i oddajemy
     * 1,0, zamiast raportować nieskończoność.
     */
    public function spreadRatio(): float
    {
        return $this->minimum <= 0.0 ? 1.0 : $this->maximum / $this->minimum;
    }

    /**
     * Pomiar jest niewiarygodny, gdy rozrzut przekracza **oba** progi: względny
     * i bezwzględny. Jeden bez drugiego myli — sam iloraz zapala się na
     * milisekundowych scenariuszach, a sama różnica bezwzględna przepuszczałaby
     * wahanie 40 ms przy klatce, która trwa 45.
     */
    public function isUnstable(): bool
    {
        return $this->count > 1
            && $this->spreadRatio() > self::UNSTABLE_SPREAD_RATIO
            && $this->maximum - $this->minimum > self::UNSTABLE_SPREAD_MILLISECONDS;
    }

    /** Udział tej wielkości w innej, w procentach — do kolumny „udział faz”. */
    public function shareOf(self $whole): float
    {
        return $whole->median <= 0.0 ? 0.0 : $this->median / $whole->median * 100.0;
    }

    /** @param non-empty-list<float> $sorted */
    private static function medianOfSorted(array $sorted): float
    {
        $count = count($sorted);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $sorted[$middle];
        }

        return ($sorted[$middle - 1] + $sorted[$middle]) / 2.0;
    }
}
