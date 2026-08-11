<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Container;

/**
 * Rozdział miejsca między uczestników — **jedna reguła na obie osie**.
 *
 * Rachunek nie jest nowy: stał od kroku 18 w `VStack` jako prywatny rozdział
 * wierszy, a przedtem — do kroku 18 — jako pętla z czterema warunkami
 * w `HudFrameLayoutService`. Krok 27 wyprowadził go tutaj, bo kolumny tabeli
 * potrzebują dokładnie tego samego, a dwa źródła tej samej reguły musiałyby się
 * pilnować nawzajem przy każdej zmianie. Przy okazji rachunek dał się wreszcie
 * sprawdzić testem bez rysowania czegokolwiek.
 *
 * Reguła w trzech zdaniach, bo dokładnie tyle jej jest:
 *
 * 1. uczestnicy o podanej mierze biorą swoje, elastyczni dzielą resztę;
 * 2. gdy miejsca brakuje, oddają je w kolejności `yieldOrder` — każdy aż do
 *    swojego minimum, a dopiero potem ustępuje następny;
 * 3. uczestnik, któremu po oddaniu zostałoby mniej niż minimum, **znika
 *    w całości**, a zwolnione miejsce wraca do elastycznych.
 *
 * Punkt trzeci jest sednem i nie jest szczegółem arytmetycznym. Pas podglądu
 * wysoki na dwa wiersze pokazuje pasek obwódki i nic więcej; kolumna z datą
 * szeroka na cztery kolumny pokazuje „202…”. Jedno i drugie jest **gorsze niż
 * nieobecność** — projekt rozstrzygnął to w kroku 12 dla pasa podglądu, w 13 dla
 * drabinki stref i utrzymuje w kroku 27 dla kolumn.
 */
final class Distribution
{
    private function __construct()
    {
    }

    /**
     * Miary uczestników przy zadanej wielkości całości.
     *
     * @param list<Span> $spans
     *
     * @return list<int> miara każdego uczestnika, w kolejności zadeklarowania
     */
    public static function of(array $spans, int $size): array
    {
        $size = max(0, $size);
        $sizes = [];
        $flexible = [];

        foreach ($spans as $index => $span) {
            if ($span->isFlexible()) {
                $flexible[] = $index;
                $sizes[$index] = $span->minimum;

                continue;
            }

            $sizes[$index] = max($span->minimum, $span->preferred ?? 0);
        }

        $used = array_sum($sizes);

        if ($used <= $size) {
            return self::spread($sizes, $flexible, $size - $used);
        }

        return self::shrink($spans, $sizes, $size, $used - $size);
    }

    /**
     * Nadmiar idzie do uczestników elastycznych — po równo, a reszta z dzielenia
     * do pierwszych z nich. Gdy elastycznego nie ma, nadmiar zostaje
     * niewykorzystany: podział nie rozciąga tych, którzy o to nie prosili.
     *
     * @param array<int, int> $sizes
     * @param list<int>       $flexible
     *
     * @return list<int>
     */
    private static function spread(array $sizes, array $flexible, int $spare): array
    {
        $count = count($flexible);

        if ($count > 0 && $spare > 0) {
            $each = intdiv($spare, $count);
            $extra = $spare - $each * $count;

            foreach ($flexible as $position => $index) {
                $sizes[$index] += $each + ($position < $extra ? 1 : 0);
            }
        }

        return array_values($sizes);
    }

    /**
     * Odbiera miejsce w kolejności ustępowania — **albo tyle, ile trzeba, albo
     * wszystko**.
     *
     * @param list<Span>      $spans
     * @param array<int, int> $sizes
     *
     * @return list<int>
     */
    private static function shrink(array $spans, array $sizes, int $size, int $excess): array
    {
        foreach (self::byYieldOrder($spans) as $index) {
            if ($excess <= 0) {
                break;
            }

            $spare = $sizes[$index] - $spans[$index]->minimum;

            if ($spare >= $excess) {
                $sizes[$index] -= $excess;
                $excess = 0;

                continue;
            }

            $excess -= $sizes[$index];
            $sizes[$index] = 0;
        }

        return self::spread($sizes, self::flexibleIndexes($spans), max(0, $size - array_sum($sizes)));
    }

    /**
     * @param list<Span> $spans
     *
     * @return list<int>
     */
    private static function flexibleIndexes(array $spans): array
    {
        $indexes = [];

        foreach ($spans as $index => $span) {
            if ($span->isFlexible()) {
                $indexes[] = $index;
            }
        }

        return $indexes;
    }

    /**
     * @param list<Span> $spans
     *
     * @return list<int> indeksy od ustępującego najchętniej
     */
    private static function byYieldOrder(array $spans): array
    {
        $order = array_keys($spans);

        usort($order, static fn (int $a, int $b): int => $spans[$a]->yieldOrder <=> $spans[$b]->yieldOrder);

        return $order;
    }
}
