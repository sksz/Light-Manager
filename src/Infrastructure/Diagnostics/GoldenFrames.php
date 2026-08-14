<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Złote klatki scenariuszy: serializacja prymitywów zapisana w pliku i
 * porównywana przez test (krok 38, D64).
 *
 * `ScenarioFactory` buduje klatki **deterministycznie** — bajt w bajt te same
 * przy każdym uruchomieniu — więc ten sam katalog scenariuszy, który służy
 * pomiarom, służy tu testom. Złoty plik łapie każdą niezamierzoną zmianę
 * treści klatki **niezależnie od renderera**: zmiana wysokości panelu, zgubiony
 * suwak czy przesunięty napis wychodzą w nim natychmiast, choć w czasach nie
 * widać ich wcale.
 *
 * **Regeneracja idzie osobnym poleceniem** (`./bin/render-bench --golden-save`)
 * i nigdy nie dzieje się sama. To nie jest ostrożność teoretyczna: złoty plik
 * regenerowany automatem przestaje być testem, bo zapisuje każdą zmianę —
 * także tę, której nikt nie chciał. Kto regeneruje, ma najpierw **przeczytać**
 * różnicę.
 *
 * Siatka jest tu mniejsza niż domyślna siatka pomiaru i to jest świadome: złoty
 * plik ma się czytać w `diff`, a nie zajmować ekran. Zmiana tych stałych
 * unieważnia wszystkie pliki naraz, więc jest decyzją, nie poprawką.
 */
final class GoldenFrames
{
    public const COLUMNS = 100;

    public const ROWS = 30;

    private const EXTENSION = '.txt';

    public function __construct(
        private readonly string $directory,
    ) {
    }

    /** Domyślne miejsce: `tests/Golden/` w korzeniu repozytorium. */
    public static function default(): self
    {
        return new self(dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'Golden');
    }

    public function path(Scenario $scenario): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $scenario->value . self::EXTENSION;
    }

    /**
     * Klatka scenariusza spisana tekstem — **jedno** źródło zarówno dla zapisu,
     * jak i dla porównania w teście. Dwa miejsca budujące tę treść rozjechałyby
     * się przy pierwszej zmianie osi.
     */
    public function textOf(Scenario $scenario): string
    {
        $options = new BenchmarkOptions(columns: self::COLUMNS, rows: self::ROWS);

        return (new FrameSerializer())->toText((new ScenarioFactory($options))->build($scenario)->frame);
    }

    public function read(Scenario $scenario): ?string
    {
        $path = $this->path($scenario);

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    /**
     * @return string ścieżka zapisanego pliku
     *
     * @throws DiagnosticsException gdy katalogu nie da się utworzyć albo pliku zapisać
     */
    public function save(Scenario $scenario): string
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o755, true) && !is_dir($this->directory)) {
            throw DiagnosticsException::forFailedWrite($this->directory);
        }

        $path = $this->path($scenario);

        if (file_put_contents($path, $this->textOf($scenario)) === false) {
            throw DiagnosticsException::forFailedWrite($path);
        }

        return $path;
    }
}
