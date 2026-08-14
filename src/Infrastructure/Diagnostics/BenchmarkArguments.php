<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Argumenty wiersza poleceń przełożone na konfigurację przebiegu.
 *
 * Parser jest czysty — dostaje tablicę napisów, oddaje wartość — więc każda oś
 * konfiguracji z planu kroku ma test, a nie tylko obietnicę, że „da się
 * przestawić”. Nierozpoznany argument kończy się wyjątkiem zamiast cichego
 * pominięcia: pomiar uruchomiony z literówką w nazwie opcji mierzyłby coś innego
 * niż to, o co poproszono, i nikt by tego nie zauważył.
 *
 * Nazwy opcji zostają angielskie i nietłumaczone — to składnia polecenia, tak
 * samo jak nazwy plików czy argumenty `stty`. Tłumaczone są opisy w `--help`.
 */
final class BenchmarkArguments
{
    /**
     * @param list<Scenario> $scenarios
     */
    private function __construct(
        public readonly BenchmarkMode $mode,
        public readonly BenchmarkOptions $options,
        public readonly array $scenarios,
        public readonly bool $measureTransfer,
        public readonly bool $save,
        public readonly string $saveName,
        public readonly bool $compare,
        /** Pusty napis znaczy „najnowszy wzorzec w katalogu”. */
        public readonly string $comparePath,
        public readonly float $thresholdPercent,
        public readonly string $pngPath,
        public readonly Scenario $pngScenario,
        /**
         * Próg porównania zrzutów w **promilach** różniących się pikseli;
         * `null` znaczy „weź domyślny dla toru” — inny dla potoku Imagicka,
         * inny dla sterownika GPU (krok 38).
         */
        public readonly ?float $imageThresholdPerMille = null,
    ) {
    }

    /** Próg porównania zrzutów: podany osią albo domyślny dla toru. */
    public function imageThreshold(): float
    {
        return $this->imageThresholdPerMille ?? $this->options->track->defaultImageThresholdPerMille();
    }

    /**
     * @param list<string> $argv argumenty bez nazwy programu
     *
     * @throws DiagnosticsException przy nieznanej opcji albo niepoprawnej wartości
     */
    public static function parse(array $argv): self
    {
        $width = BenchmarkOptions::DEFAULT_WIDTH_PIXELS;
        $height = BenchmarkOptions::DEFAULT_HEIGHT_PIXELS;
        $columns = BenchmarkOptions::DEFAULT_COLUMNS;
        $rows = BenchmarkOptions::DEFAULT_ROWS;
        $theme = 'grafit';
        $textAntialias = false;
        $strokeAntialias = true;
        $palette = 64;
        $font = null;
        $iterations = BenchmarkOptions::DEFAULT_ITERATIONS;
        $warmup = BenchmarkOptions::DEFAULT_WARMUP_ITERATIONS;

        $mode = BenchmarkMode::Run;
        $scenarios = Scenario::all();
        $windowed = false;
        $textual = false;
        $loop = false;
        $transfer = false;
        $save = false;
        $saveName = 'render';
        $compare = false;
        $comparePath = '';
        $threshold = BaselineComparison::DEFAULT_THRESHOLD_PERCENT;
        $pngPath = '';
        $pngScenario = Scenario::ChromeWithText;
        $imageThreshold = null;

        foreach ($argv as $argument) {
            [$name, $value, $hasValue] = self::split($argument);

            switch ($name) {
                case '--help':
                case '-h':
                    $mode = BenchmarkMode::Help;

                    break;
                case '--width':
                    $width = self::positiveInt($argument, $value);

                    break;
                case '--height':
                    $height = self::positiveInt($argument, $value);

                    break;
                case '--size':
                    [$width, $height] = self::pair($argument, $value);

                    break;
                case '--columns':
                    $columns = self::positiveInt($argument, $value);

                    break;
                case '--rows':
                    $rows = self::positiveInt($argument, $value);

                    break;
                case '--grid':
                    [$columns, $rows] = self::pair($argument, $value);

                    break;
                case '--theme':
                    $theme = self::nonEmpty($argument, $value);

                    break;
                case '--palette':
                    $palette = self::positiveInt($argument, $value);

                    break;
                case '--text-aa':
                    $textAntialias = self::flag($argument, $value, $hasValue);

                    break;
                case '--stroke-aa':
                    $strokeAntialias = self::flag($argument, $value, $hasValue);

                    break;
                case '--font':
                    $font = self::nonEmpty($argument, $value);

                    break;
                case '--iterations':
                    $iterations = self::positiveInt($argument, $value);

                    break;
                case '--warmup':
                    // Zero jest tu poprawne: „zmierz bez rozgrzewki” to sensowna
                    // prośba, choć zwykle zły pomysł.
                    $warmup = self::nonNegativeInt($argument, $value);

                    break;
                case '--scenarios':
                    $scenarios = Scenario::fromNames(self::slugList($argument, $value));

                    break;
                case '--window':
                    $windowed = self::flag($argument, $value, $hasValue);

                    break;
                case '--text':
                    $textual = self::flag($argument, $value, $hasValue);

                    break;
                case '--loop':
                    $loop = self::flag($argument, $value, $hasValue);

                    break;
                case '--transfer':
                    $transfer = self::flag($argument, $value, $hasValue);

                    break;
                case '--save':
                    $save = true;
                    $saveName = $hasValue ? self::nonEmpty($argument, $value) : $saveName;

                    break;
                case '--compare':
                    $compare = true;
                    $comparePath = $hasValue ? self::nonEmpty($argument, $value) : '';

                    break;
                case '--threshold':
                    $threshold = (float) self::positiveInt($argument, $value);

                    break;
                case '--png':
                    $mode = BenchmarkMode::Snapshot;
                    $pngPath = self::nonEmpty($argument, $value);

                    break;
                case '--golden-save':
                    $mode = BenchmarkMode::GoldenSave;

                    break;
                case '--png-save':
                    $mode = BenchmarkMode::ImageSave;

                    break;
                case '--png-compare':
                    $mode = BenchmarkMode::ImageCompare;

                    break;
                case '--png-threshold':
                    // Ułamek promila ma sens: przy płótnie 1000×600 jeden promil
                    // to sześćset pikseli, a zjedzony obrys bywa mniejszy.
                    $imageThreshold = self::nonNegativeFraction($argument, $value);

                    break;
                case '--scenario':
                    $pngScenario = Scenario::fromNames([self::nonEmpty($argument, $value)])[0];

                    break;
                default:
                    throw DiagnosticsException::forInvalidArgument($argument);
            }
        }

        // Tor jest jeden na przebieg. Dwa naraz nie znaczą „zmierz oba” — nie ma
        // takiej tabeli, bo liczby torów są nieporównywalne.
        if (count(array_filter([$windowed, $textual, $loop])) > 1) {
            throw DiagnosticsException::forInvalidArgument($loop ? '--loop' : '--text');
        }

        // Takt pętli nie ma ani bajtów do wypchnięcia, ani obrazu do zapisania:
        // klatka kończy w nim swoją drogę jako prymitywy.
        if ($loop && ($transfer || self::wantsImage($mode))) {
            throw DiagnosticsException::forInvalidArgument('--loop');
        }

        // Zrzut płótna (`--png`) bierze obraz Imagicka, którego w oknie nie ma —
        // tam zrzut robi się odczytem bufora GPU, czyli trybami `--png-save`
        // i `--png-compare`. Przesył mierzy się natomiast w obu torach
        // terminalowych: w tekstowym idą do terminala prawdziwe bajty i ich
        // koszt jest tak samo prawdziwy.
        if ($windowed && ($transfer || $mode === BenchmarkMode::Snapshot)) {
            throw DiagnosticsException::forInvalidArgument(
                $transfer ? '--transfer' : '--png',
            );
        }

        // Tor tekstowy nie rysuje obrazu w ogóle — jego klatka to znaki
        // i atrybuty. Zrzut z niego robi dopiero żywa aplikacja, rasteryzując
        // bufor ANSI (krok 38, D64); narzędzie pomiarowe mówi to wprost zamiast
        // podstawiać obraz z innego toru.
        if ($textual && self::wantsImage($mode)) {
            throw DiagnosticsException::forInvalidArgument('--text');
        }

        $track = match (true) {
            $windowed => BenchmarkTrack::Window,
            $textual => BenchmarkTrack::Text,
            $loop => BenchmarkTrack::Loop,
            default => BenchmarkTrack::Sixel,
        };

        // Tor taktu mierzy **jedno**: drogę klatki od klawisza do prymitywów.
        // Osi scenariuszy w nim nie ma, bo treść składa `LoopScenarioScreen`,
        // a nie fabryka — szesnaście wierszy z tą samą liczbą byłoby tabelą
        // udającą pomiar. Wiersz zostaje jeden i nosi nazwę pełnej klatki.
        if ($loop) {
            $scenarios = [Scenario::ChromeWithText];
        }

        return new self(
            $mode,
            new BenchmarkOptions(
                $width,
                $height,
                $columns,
                $rows,
                $theme,
                $textAntialias,
                $strokeAntialias,
                $palette,
                $font,
                $iterations,
                $warmup,
                $track,
            ),
            $scenarios,
            $transfer,
            $save,
            $saveName,
            $compare,
            $comparePath,
            $threshold,
            $pngPath,
            $pngScenario,
            $imageThreshold,
        );
    }

    /** Czy tryb w ogóle potrzebuje obrazu klatki. */
    private static function wantsImage(BenchmarkMode $mode): bool
    {
        return in_array($mode, [BenchmarkMode::Snapshot, BenchmarkMode::ImageSave, BenchmarkMode::ImageCompare], true);
    }

    /** Liczba nieujemna, także ułamkowa — próg porównania zrzutów w promilach. */
    private static function nonNegativeFraction(string $argument, string $value): float
    {
        if (preg_match('/^\d+(\.\d+)?$/', $value) !== 1) {
            throw DiagnosticsException::forInvalidArgument($argument);
        }

        return (float) $value;
    }

    /** @return array{string, string, bool} nazwa, wartość, czy wartość podano */
    private static function split(string $argument): array
    {
        $position = strpos($argument, '=');

        if ($position === false) {
            return [$argument, '', false];
        }

        return [substr($argument, 0, $position), substr($argument, $position + 1), true];
    }

    private static function positiveInt(string $argument, string $value): int
    {
        $parsed = self::nonNegativeInt($argument, $value);

        if ($parsed === 0) {
            throw DiagnosticsException::forInvalidArgument($argument);
        }

        return $parsed;
    }

    private static function nonNegativeInt(string $argument, string $value): int
    {
        if (preg_match('/^\d+$/', $value) !== 1) {
            throw DiagnosticsException::forInvalidArgument($argument);
        }

        return (int) $value;
    }

    /** @return array{int, int} */
    private static function pair(string $argument, string $value): array
    {
        $parts = explode('x', strtolower($value));

        if (count($parts) !== 2) {
            throw DiagnosticsException::forInvalidArgument($argument);
        }

        return [self::positiveInt($argument, $parts[0]), self::positiveInt($argument, $parts[1])];
    }

    private static function nonEmpty(string $argument, string $value): string
    {
        if ($value === '') {
            throw DiagnosticsException::forInvalidArgument($argument);
        }

        return $value;
    }

    /**
     * Przełącznik bez wartości znaczy „włącz”; z wartością przyjmuje `1`/`0`,
     * `tak`/`nie`, `yes`/`no`, `true`/`false`.
     */
    private static function flag(string $argument, string $value, bool $hasValue): bool
    {
        if (!$hasValue) {
            return true;
        }

        return match (strtolower($value)) {
            '1', 'tak', 'yes', 'true', 'on' => true,
            '0', 'nie', 'no', 'false', 'off' => false,
            default => throw DiagnosticsException::forInvalidArgument($argument),
        };
    }

    /** @return list<string> */
    private static function slugList(string $argument, string $value): array
    {
        $parts = array_values(array_filter(
            array_map('trim', explode(',', self::nonEmpty($argument, $value))),
            static fn (string $part): bool => $part !== '',
        ));

        if ($parts === []) {
            throw DiagnosticsException::forInvalidArgument($argument);
        }

        return $parts;
    }
}
