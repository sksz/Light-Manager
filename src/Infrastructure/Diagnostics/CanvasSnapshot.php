<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Zapisuje płótno scenariusza do pliku PNG.
 *
 * Powód istnienia tego trybu jest konkretny: najważniejsze odkrycie kroku 13 —
 * że przy 16 i 32 kolorach kwantyzator poświęca odcień obwódki i **panele znikają
 * z ekranu** — było niewidoczne w czasie ani w rozmiarze bloba. Wyszło dopiero
 * z obejrzenia renderu. Liczby nie pokazują wszystkiego, więc narzędzie musi
 * umieć pokazać obraz.
 *
 * Zrzut powstaje **przed** kwantyzacją, tak jak wymaga tego plan kroku: PNG ma
 * pokazać, co narysował enkoder, a nie co z tego zostawiła paleta. Skutki samej
 * palety ogląda się na terminalu — tam, gdzie naprawdę występują.
 */
final class CanvasSnapshot
{
    public function __construct(
        private readonly BenchmarkRunner $runner,
    ) {
    }

    /**
     * @throws DiagnosticsException gdy pliku nie da się zapisać
     */
    public function write(Scenario $scenario, string $path): void
    {
        $canvas = $this->runner->drawOnly($scenario);

        try {
            $canvas->setImageFormat('png');

            if (!$canvas->writeImage($path)) {
                throw DiagnosticsException::forFailedWrite($path);
            }
        } finally {
            $canvas->clear();
        }
    }
}
