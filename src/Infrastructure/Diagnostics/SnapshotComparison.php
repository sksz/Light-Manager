<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use Imagick;
use ImagickException;

/**
 * Porównuje zrzut bieżącej klatki z wzorcowym PNG — regresja wizualna bez
 * człowieka oglądającego obrazek (krok 38).
 *
 * Powód istnienia jest starszy od tego kroku: **najważniejsze odkrycie kroku
 * 13** — kwantyzator przy 16 i 32 kolorach zjadał odcień obwódki, przez co
 * panele znikały z ekranu — nie było widoczne ani w czasie, ani w rozmiarze
 * bloba. Wyszło z obejrzenia obrazu. Krok 16 dał zrzut, ale oglądanie zostało
 * człowiekowi; tutaj obraz staje się miarą.
 *
 * Metryka to **AE** (`METRIC_ABSOLUTEERRORMETRIC`), czyli liczba różniących się
 * pikseli, a nie średnia różnica po kanałach: cienki obrys to kilkaset pikseli
 * na sześciuset tysiącach, więc w mierze średniokwadratowej tonie, a tu widać
 * go wprost (D64).
 *
 * Narzędzie **odmawia i wskazuje pliki** — nie rysuje raportów i nie otwiera
 * niczego na ekranie.
 */
final class SnapshotComparison
{
    public function __construct(
        private readonly SnapshotStore $store,
    ) {
    }

    /**
     * @throws DiagnosticsException gdy obrazu różnicy nie da się zapisać
     */
    public function compare(
        Imagick $current,
        BenchmarkTrack $track,
        Scenario $scenario,
        float $thresholdPerMille,
        string $signature = '',
    ): SnapshotDifference {
        $path = $this->store->path($track, $scenario);

        if (!$this->store->has($track, $scenario)) {
            return SnapshotDifference::missing($scenario, $path);
        }

        $baseline = new Imagick($path);

        try {
            $width = $current->getImageWidth();
            $height = $current->getImageHeight();
            $baselineSignature = $this->store->signatureOf($baseline);

            // Ta sama reguła, którą wzorce liczbowe mają od kroku 16: pomiar
            // przy innych ustawieniach to inny pomiar, a nie regresja.
            if ($signature !== '' && $baselineSignature !== '' && $baselineSignature !== $signature) {
                return SnapshotDifference::incomparable($scenario, $path);
            }

            if ($baseline->getImageWidth() !== $width || $baseline->getImageHeight() !== $height) {
                return SnapshotDifference::resized($scenario, $path);
            }

            return $this->measured($baseline, $current, $track, $scenario, $thresholdPerMille, $width * $height);
        } finally {
            $baseline->clear();
        }
    }

    /**
     * Obraz różnicy powstaje **zawsze**, ale zapisuje się wyłącznie wtedy, gdy
     * próg został przekroczony: przy zgodnym zrzucie byłby plikiem czarnych
     * pikseli, którego nikt nie otworzy.
     */
    private function measured(
        Imagick $baseline,
        Imagick $current,
        BenchmarkTrack $track,
        Scenario $scenario,
        float $thresholdPerMille,
        int $totalPixels,
    ): SnapshotDifference {
        try {
            /** @var array{Imagick, float} $comparison */
            $comparison = $baseline->compareImages($current, Imagick::METRIC_ABSOLUTEERRORMETRIC);
        } catch (ImagickException) {
            // Obrazy o różnej głębi albo przestrzeni barw — z punktu widzenia
            // regresji to ta sama odpowiedź, co inny rozmiar: nie ma czego
            // z czym porównać.
            return SnapshotDifference::resized($scenario, $this->store->path($track, $scenario));
        }

        [$difference, $distortion] = $comparison;
        $differingPixels = (int) round($distortion);
        $exceeded = $differingPixels > $totalPixels * $thresholdPerMille / 1000.0;

        try {
            $differencePath = $exceeded
                ? $this->store->write($difference, $this->store->differencePath($track, $scenario))
                : null;
        } finally {
            $difference->clear();
        }

        return SnapshotDifference::compared(
            $scenario,
            $differingPixels,
            $totalPixels,
            $this->store->path($track, $scenario),
            $thresholdPerMille,
            $differencePath,
        );
    }
}
