<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use Imagick;
use LightManager\Infrastructure\Imagick\SixelFrameEncoder;
use LightManager\Infrastructure\Rendering\RenderingOptions;

/**
 * Przepuszcza scenariusze przez potok renderowania i zbiera czasy faz.
 *
 * Instrumentacja żyje w całości tutaj: enkoder jest wołany trzema publicznymi
 * krokami, między którymi stoi zegar. W samym enkoderze nie ma ani jednego
 * wywołania pomiarowego (D28) — dokładnie po to został rozbity.
 *
 * Metodyka, wymuszona przez klasę, a nie zostawiona dobrym chęciom:
 *
 * - **rozgrzewka przed pomiarem** — pierwsza klatka płaci za wybór fontu i
 *   pomiar szerokości napisów, a jej próbka jest odrzucana;
 * - **ta sama instancja enkodera przez cały przebieg** — bo w aplikacji też
 *   żyje ona przez całe uruchomienie razem ze swoimi pamięciami podręcznymi;
 * - **płótno zwalniane w `finally`** — przebieg, który wywróci się w połowie,
 *   nie zostawia po sobie zajętej pamięci ImageMagicka.
 */
final class BenchmarkRunner
{
    public function __construct(
        private readonly SixelFrameEncoder $encoder,
        private readonly ScenarioFactory $factory,
        private readonly BenchmarkOptions $options,
        private readonly RenderingOptions $rendering,
    ) {
    }

    /**
     * @param list<Scenario> $scenarios
     *
     * @return list<ScenarioResult>
     */
    public function run(array $scenarios): array
    {
        $results = [];

        foreach ($scenarios as $scenario) {
            $results[] = $this->runOne($scenario);
        }

        return $results;
    }

    private function runOne(Scenario $scenario): ScenarioResult
    {
        $prepared = $this->factory->build($scenario);

        for ($index = 0; $index < $this->options->warmupIterations; ++$index) {
            $this->sample($prepared);
        }

        $samples = [];

        for ($index = 0; $index < max(1, $this->options->iterations); ++$index) {
            $samples[] = $this->sample($prepared);
        }

        return ScenarioResult::fromSamples($scenario, $samples);
    }

    /**
     * Jeden pełny przebieg potoku z zegarem między fazami.
     *
     * Kolejność i warunki są tu identyczne jak w `SixelFrameEncoder::encode()`,
     * łącznie z pytaniem enkodera o to, czy w klatce wylądowała bitmapa —
     * gdybyśmy zgadywali tę odpowiedź, scenariusz z miniaturą mógłby zostać
     * zmierzony na innej palecie niż ta, której użyje aplikacja.
     */
    private function sample(ScenarioFrame $prepared): PhaseSample
    {
        $started = microtime(true);

        $canvas = $this->encoder->drawCanvas(
            $prepared->frame,
            $this->rendering,
            $this->options->widthPixels,
            $this->options->heightPixels,
            $prepared->rows,
            $prepared->columns,
        );

        try {
            $drawn = microtime(true);

            $this->encoder->quantizeCanvas($canvas, $this->encoder->canvasCarriesBitmap());
            $quantized = microtime(true);

            $blob = $this->encoder->toSixel($canvas);
            $encoded = microtime(true);

            return new PhaseSample(
                ($drawn - $started) * 1000,
                ($quantized - $drawn) * 1000,
                ($encoded - $quantized) * 1000,
                strlen($blob),
            );
        } finally {
            $canvas->clear();
        }
    }

    /**
     * Płótno wybranego scenariusza **przed** kwantyzacją — materiał na zrzut do
     * PNG. Zwolnienie należy do wołającego, tak jak przy `drawCanvas()`.
     */
    public function drawOnly(Scenario $scenario): Imagick
    {
        $prepared = $this->factory->build($scenario);

        return $this->encoder->drawCanvas(
            $prepared->frame,
            $this->rendering,
            $this->options->widthPixels,
            $this->options->heightPixels,
            $prepared->rows,
            $prepared->columns,
        );
    }

    /** Gotowe bajty Sixela jednego scenariusza — wejście do pomiaru przesyłu. */
    public function blobFor(Scenario $scenario): string
    {
        $prepared = $this->factory->build($scenario);

        $canvas = $this->encoder->drawCanvas(
            $prepared->frame,
            $this->rendering,
            $this->options->widthPixels,
            $this->options->heightPixels,
            $prepared->rows,
            $prepared->columns,
        );

        try {
            $this->encoder->quantizeCanvas($canvas, $this->encoder->canvasCarriesBitmap());

            return $this->encoder->toSixel($canvas);
        } finally {
            $canvas->clear();
        }
    }
}
