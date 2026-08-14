<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use Imagick;

/**
 * Wzorcowe zrzuty klatek — `docs/pomiary/wzorce-png/<tor>-<scenariusz>.png`.
 *
 * Leżą w repozytorium z tego samego powodu, co wzorce liczbowe (D33): wzorzec
 * trzymany poza repozytorium przepada razem z maszyną, a wtedy „przed i po”
 * sprowadza się do wrażeń. Tor jest w nazwie, bo obraz sixelowy i okienkowy tej
 * samej klatki to **dwa różne obrazy** — inny rasteryzator, inny wygładzacz.
 *
 * Obrazy różnicy powstają obok, z przyrostkiem `-roznica`, i **nie wchodzą do
 * repozytorium**: są wynikiem jednego przebiegu, a nie punktem odniesienia.
 */
final class SnapshotStore
{
    public const DIFFERENCE_SUFFIX = '-roznica';

    /**
     * Podpis konfiguracji wpisany w metadane PNG (chunk `tEXt`).
     *
     * Bez niego wzorcowy zrzut byłby jedynym wzorcem w projekcie, który nie wie,
     * przy jakich ustawieniach powstał — a porównanie klatki w motywie „nordyk”
     * z wzorcem „grafit” pokazałoby regresję wyglądu tam, gdzie zmieniła się
     * wyłącznie prośba użytkownika. Wzorce liczbowe pilnują tego od kroku 16
     * (`isComparableWith()`); obrazy dostają tę samą regułę w kroku 38.
     */
    public const SIGNATURE_PROPERTY = 'lm:signature';

    private const EXTENSION = '.png';

    public function __construct(
        private readonly string $directory,
    ) {
    }

    /** Domyślne miejsce: `docs/pomiary/wzorce-png/` w korzeniu repozytorium. */
    public static function default(): self
    {
        return new self(
            dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'docs'
            . DIRECTORY_SEPARATOR . 'pomiary' . DIRECTORY_SEPARATOR . 'wzorce-png',
        );
    }

    public function path(BenchmarkTrack $track, Scenario $scenario): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $track->value . '-' . $scenario->value . self::EXTENSION;
    }

    public function differencePath(BenchmarkTrack $track, Scenario $scenario): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $track->value . '-' . $scenario->value
            . self::DIFFERENCE_SUFFIX . self::EXTENSION;
    }

    public function has(BenchmarkTrack $track, Scenario $scenario): bool
    {
        return is_file($this->path($track, $scenario));
    }

    /**
     * @return string ścieżka zapisanego pliku
     *
     * @throws DiagnosticsException gdy katalogu nie da się utworzyć albo pliku zapisać
     */
    public function save(Imagick $image, BenchmarkTrack $track, Scenario $scenario, string $signature): string
    {
        $image->setImageProperty(self::SIGNATURE_PROPERTY, $signature);

        return $this->write($image, $this->path($track, $scenario));
    }

    /** Podpis konfiguracji zapisany we wzorcu; pusty, gdy plik go nie niesie. */
    public function signatureOf(Imagick $baseline): string
    {
        return $baseline->getImageProperty(self::SIGNATURE_PROPERTY) ?: '';
    }

    /**
     * @throws DiagnosticsException gdy pliku nie da się zapisać
     */
    public function write(Imagick $image, string $path): string
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw DiagnosticsException::forFailedWrite($directory);
        }

        $image->setImageFormat('png');

        if (!$image->writeImage($path)) {
            throw DiagnosticsException::forFailedWrite($path);
        }

        return $path;
    }
}
