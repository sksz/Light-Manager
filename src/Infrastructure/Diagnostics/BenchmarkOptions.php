<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Infrastructure\Rendering\RenderingOptions;
use LightManager\Infrastructure\Rendering\Theme;

/**
 * Osie konfiguracji pomiaru — wszystko, co da się przestawić z linii poleceń.
 *
 * Wartości domyślne odtwarzają punkt odniesienia z kroku 13 (płótno 1000×600,
 * siatka 166×46, czyli komórka 6×13 px). Dzięki temu pierwszy przebieg narzędzia
 * jest od razu porównywalny z liczbami zapisanymi w planie kroku 16; rozbieżność
 * będzie sygnałem, że mierzymy coś innego, niż mierzono wtedy.
 *
 * Siatka znakowa jest tu osią niezależną od rozmiaru płótna, bo koszt tekstu
 * zależy od liczby znaków, a nie od powierzchni obrazu.
 */
final class BenchmarkOptions
{
    public const DEFAULT_WIDTH_PIXELS = 1000;

    public const DEFAULT_HEIGHT_PIXELS = 600;

    public const DEFAULT_COLUMNS = 166;

    public const DEFAULT_ROWS = 46;

    /** Plan wymaga mediany z co najmniej piętnastu przebiegów. */
    public const DEFAULT_ITERATIONS = 15;

    /**
     * Pierwsza klatka płaci za wybór fontu i pomiar szerokości napisów, więc
     * wliczona do mediany zaburza wynik. Trzy przebiegi wystarczą, by pamięci
     * podręczne enkodera się zapełniły.
     */
    public const DEFAULT_WARMUP_ITERATIONS = 3;

    public function __construct(
        public readonly int $widthPixels = self::DEFAULT_WIDTH_PIXELS,
        public readonly int $heightPixels = self::DEFAULT_HEIGHT_PIXELS,
        public readonly int $columns = self::DEFAULT_COLUMNS,
        public readonly int $rows = self::DEFAULT_ROWS,
        public readonly string $themeName = 'grafit',
        public readonly bool $textAntialias = false,
        public readonly bool $strokeAntialias = true,
        public readonly int $paletteColors = 64,
        /** `null` znaczy „font wybrany automatycznie z listy preferencji”. */
        public readonly ?string $font = null,
        public readonly int $iterations = self::DEFAULT_ITERATIONS,
        public readonly int $warmupIterations = self::DEFAULT_WARMUP_ITERATIONS,
        /**
         * Tor okienkowy (krok 35, D54): klatka idzie przez renderer OpenGL
         * do ukrytego okna zamiast przez potok Sixela. Osobna oś, bo wyniki
         * obu torów nie mają prawa się porównywać.
         */
        public readonly bool $windowed = false,
    ) {
    }

    public function toRenderingOptions(Theme $theme): RenderingOptions
    {
        return new RenderingOptions(
            $theme,
            $this->textAntialias,
            $this->strokeAntialias,
            $this->paletteColors,
            $this->font,
        );
    }

    /**
     * Konfiguracja spisana w jednej linii — trafia do nagłówka tabeli i do pliku
     * wzorca, żeby porównanie dwóch przebiegów o różnych ustawieniach dało się
     * rozpoznać jako nieporównywalne.
     *
     * Zapis jest techniczny i nietłumaczony, jak argumenty `stty` w komunikatach
     * wyjątków: to identyfikator konfiguracji, który ma wyglądać tak samo w
     * pliku wzorca sprzed roku i na dzisiejszym wydruku, niezależnie od języka
     * interfejsu.
     */
    public function signature(): string
    {
        return sprintf(
            '%dx%dpx %dx%d theme=%s palette=%d textAA=%d strokeAA=%d font=%s%s',
            $this->widthPixels,
            $this->heightPixels,
            $this->columns,
            $this->rows,
            $this->themeName,
            $this->paletteColors,
            $this->textAntialias ? 1 : 0,
            $this->strokeAntialias ? 1 : 0,
            $this->font ?? 'auto',
            $this->windowed ? ' window' : '',
        );
    }

    /** @return array<string, string|int|bool|null> */
    public function toArray(): array
    {
        return [
            'widthPixels' => $this->widthPixels,
            'heightPixels' => $this->heightPixels,
            'columns' => $this->columns,
            'rows' => $this->rows,
            'theme' => $this->themeName,
            'textAntialias' => $this->textAntialias,
            'strokeAntialias' => $this->strokeAntialias,
            'paletteColors' => $this->paletteColors,
            'font' => $this->font,
            'iterations' => $this->iterations,
            'warmupIterations' => $this->warmupIterations,
            'windowed' => $this->windowed,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $font = $data['font'] ?? null;

        return new self(
            JsonValue::int($data, 'widthPixels', self::DEFAULT_WIDTH_PIXELS),
            JsonValue::int($data, 'heightPixels', self::DEFAULT_HEIGHT_PIXELS),
            JsonValue::int($data, 'columns', self::DEFAULT_COLUMNS),
            JsonValue::int($data, 'rows', self::DEFAULT_ROWS),
            JsonValue::string($data, 'theme', 'grafit'),
            JsonValue::bool($data, 'textAntialias'),
            JsonValue::bool($data, 'strokeAntialias', true),
            JsonValue::int($data, 'paletteColors', 64),
            is_string($font) ? $font : null,
            JsonValue::int($data, 'iterations', self::DEFAULT_ITERATIONS),
            JsonValue::int($data, 'warmupIterations', self::DEFAULT_WARMUP_ITERATIONS),
            JsonValue::bool($data, 'windowed'),
        );
    }
}
