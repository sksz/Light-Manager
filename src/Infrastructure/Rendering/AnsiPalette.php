<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Rendering;

/**
 * Tłumaczy kolory motywu na kody ANSI dla trybu tekstowego.
 *
 * Motyw opisuje kolory jako `#rrggbb`, bo tak potrzebuje ich Imagick. Terminal
 * przyjmuje numer z palety, więc trzeba znaleźć najbliższy — w palecie 256
 * kolorów (kostka 6×6×6 plus 24 stopnie szarości) albo, gdy terminal jej nie
 * deklaruje, wśród szesnastu kolorów podstawowych.
 *
 * Sama konwersja to arytmetyka bez wejścia-wyjścia, więc daje się przetestować
 * bez terminala.
 */
final class AnsiPalette
{
    public const RESET = "\e[0m";

    /** Progi kostki 6×6×6 w palecie xterm — nierówne, stąd tablica zamiast wzoru. */
    private const CUBE_LEVELS = [0, 95, 135, 175, 215, 255];

    /**
     * Poniżej tego nasycenia kolor idzie w szarości. Próg jest dobrany tak, by
     * granatowawe tło zaznaczenia (`#2a2f38`, nasycenie 0,25) zostało szarością,
     * a czerwień komunikatu (`#e0645c`, 0,59) — czerwienią.
     */
    private const CHROMATIC_SATURATION = 0.35;

    /** Powyżej tej jasności kolor dostaje wariant rozjaśniony (kody `90–97`). */
    private const BRIGHT_VALUE = 0.75;

    public function __construct(
        private readonly bool $supports256,
    ) {
    }

    /**
     * Terminal ogłasza paletę przez `TERM` albo `COLORTERM`. Gdy milczy,
     * zakładamy szesnaście kolorów — pomyłka w tę stronę daje kolory zgrubne,
     * pomyłka w drugą daje kody, których terminal nie rozumie, i śmieci na
     * ekranie.
     */
    public static function fromEnvironment(): self
    {
        $term = (string) getenv('TERM');
        $colorTerm = (string) getenv('COLORTERM');

        return new self(
            str_contains($term, '256color')
            || str_contains($term, 'direct')
            || in_array($colorTerm, ['truecolor', '24bit'], true),
        );
    }

    public function foreground(string $hex): string
    {
        [$red, $green, $blue] = $this->channels($hex);

        if ($this->supports256) {
            return sprintf("\e[38;5;%dm", $this->index256($red, $green, $blue));
        }

        $basic = $this->basicIndex($red, $green, $blue);

        return sprintf("\e[%dm", $basic < 8 ? 30 + $basic : 90 + $basic - 8);
    }

    public function background(string $hex): string
    {
        [$red, $green, $blue] = $this->channels($hex);

        if ($this->supports256) {
            return sprintf("\e[48;5;%dm", $this->index256($red, $green, $blue));
        }

        $basic = $this->basicIndex($red, $green, $blue);

        return sprintf("\e[%dm", $basic < 8 ? 40 + $basic : 100 + $basic - 8);
    }

    /**
     * Kandydatów jest dwóch: najbliższy punkt kostki kolorów i najbliższy
     * stopień szarości. Szarości mają gęstszą siatkę (co ~10 zamiast co ~40),
     * więc dla stonowanego motywu wygrywają — ale tylko wtedy, gdy naprawdę są
     * bliżej.
     *
     * @return int<0, 255>
     */
    private function index256(int $red, int $green, int $blue): int
    {
        $cube = [
            $this->nearestCubeLevel($red),
            $this->nearestCubeLevel($green),
            $this->nearestCubeLevel($blue),
        ];

        $cubeIndex = 16 + 36 * $cube[0] + 6 * $cube[1] + $cube[2];
        $cubeDistance = $this->distance(
            [self::CUBE_LEVELS[$cube[0]], self::CUBE_LEVELS[$cube[1]], self::CUBE_LEVELS[$cube[2]]],
            [$red, $green, $blue],
        );

        $gray = (int) round(($red + $green + $blue) / 3);
        $step = max(0, min(23, (int) round(($gray - 8) / 10)));
        $grayValue = 8 + $step * 10;
        $grayDistance = $this->distance([$grayValue, $grayValue, $grayValue], [$red, $green, $blue]);

        $index = $grayDistance < $cubeDistance ? 232 + $step : $cubeIndex;

        return max(0, min(255, $index));
    }

    /**
     * Wśród szesnastu kolorów szukamy po odcieniu, nie po odległości w RGB.
     * Odległość euklidesowa daje tu wyniki wprost szkodliwe: czerwień `#e0645c`
     * leży bliżej średniej szarości niż czystej czerwieni, więc komunikat o
     * błędzie wyszedłby szary. Odcień rozstrzyga pierwszy, jasność wybiera
     * wariant zwykły albo rozjaśniony, a kolory bez wyraźnego odcienia trafiają
     * w rampę szarości.
     */
    private function basicIndex(int $red, int $green, int $blue): int
    {
        [$hue, $saturation, $value] = $this->hsv($red, $green, $blue);

        if ($saturation < self::CHROMATIC_SATURATION) {
            return match (true) {
                $value < 0.2 => 0,
                $value < 0.5 => 8,
                $value < 0.85 => 7,
                default => 15,
            };
        }

        $base = match (true) {
            $hue < 30.0, $hue >= 330.0 => 1,
            $hue < 90.0 => 3,
            $hue < 150.0 => 2,
            $hue < 210.0 => 6,
            $hue < 270.0 => 4,
            default => 5,
        };

        return $value >= self::BRIGHT_VALUE ? $base + 8 : $base;
    }

    /** @return array{float, float, float} odcień 0–360, nasycenie i jasność 0–1 */
    private function hsv(int $red, int $green, int $blue): array
    {
        $high = max($red, $green, $blue);
        $low = min($red, $green, $blue);
        $span = $high - $low;

        if ($high === 0 || $span === 0) {
            return [0.0, 0.0, $high / 255];
        }

        $hue = match ($high) {
            $red => 60.0 * fmod(($green - $blue) / $span, 6.0),
            $green => 60.0 * (($blue - $red) / $span + 2.0),
            default => 60.0 * (($red - $green) / $span + 4.0),
        };

        return [$hue < 0.0 ? $hue + 360.0 : $hue, $span / $high, $high / 255];
    }

    private function nearestCubeLevel(int $value): int
    {
        $best = 0;
        $bestDistance = PHP_INT_MAX;

        foreach (self::CUBE_LEVELS as $index => $level) {
            $distance = abs($level - $value);

            if ($distance < $bestDistance) {
                $best = $index;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * @param array{int, int, int} $first
     * @param array{int, int, int} $second
     */
    private function distance(array $first, array $second): int
    {
        return ($first[0] - $second[0]) ** 2
            + ($first[1] - $second[1]) ** 2
            + ($first[2] - $second[2]) ** 2;
    }

    /** @return array{int, int, int} */
    private function channels(string $hex): array
    {
        $digits = ltrim($hex, '#');

        return [
            (int) hexdec(substr($digits, 0, 2)),
            (int) hexdec(substr($digits, 2, 2)),
            (int) hexdec(substr($digits, 4, 2)),
        ];
    }
}
