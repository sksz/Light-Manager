<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use Imagick;
use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Infrastructure\Imagick\SixelFrameEncoder;
use LightManager\Infrastructure\Rendering\RenderingOptions;

/**
 * Tor sixelowy: scenariusze przepuszczone przez potok Imagicka z zegarem między
 * fazami.
 *
 * Instrumentacja żyje w całości tutaj: enkoder jest wołany trzema publicznymi
 * krokami, między którymi stoi zegar. W samym enkoderze nie ma ani jednego
 * wywołania pomiarowego (D28) — dokładnie po to został rozbity.
 *
 * Metodyka wspólna wszystkim torom (rozgrzewka, zimna klatka, szczyt pamięci,
 * proces towarzyszący) mieszka w `AbstractBenchmarkRunner`. Tutaj zostaje to,
 * co sixelowe: **ta sama instancja enkodera przez cały przebieg** — bo
 * w aplikacji też żyje ona przez całe uruchomienie razem ze swoimi pamięciami
 * podręcznymi — i **płótno zwalniane w `finally`**, żeby przebieg przerwany
 * w połowie nie zostawiał zajętej pamięci ImageMagicka.
 */
final class BenchmarkRunner extends AbstractBenchmarkRunner implements ScenarioImageSource
{
    public function __construct(
        private readonly SixelFrameEncoder $encoder,
        ScenarioFactory $factory,
        BenchmarkOptions $options,
        private readonly RenderingOptions $rendering,
        ?BackgroundProcessPort $processes = null,
    ) {
        parent::__construct($factory, $options, $processes);
    }

    /**
     * Jeden pełny przebieg potoku z zegarem między fazami.
     *
     * Kolejność i warunki są tu identyczne jak w `SixelFrameEncoder::encode()`,
     * łącznie z pytaniem enkodera o to, czy w klatce wylądowała bitmapa —
     * gdybyśmy zgadywali tę odpowiedź, scenariusz z miniaturą mógłby zostać
     * zmierzony na innej palecie niż ta, której użyje aplikacja.
     */
    protected function sample(ScenarioFrame $prepared, ?BackgroundHandle $work = null): PhaseSample
    {
        $started = microtime(true);

        $this->pollCompanion($work);

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

    /**
     * Płótno **po kwantyzacji** — obraz do porównania z wzorcem (krok 38).
     *
     * Różnica wobec `drawOnly()` jest tu całą treścią: tryb `--png` pokazuje, co
     * narysował enkoder, a porównanie regresji musi patrzeć na to, co z tego
     * zostawiła paleta. Odkrycie kroku 13 — zjedzony odcień obwódki — powstaje
     * dokładnie w tym kroku potoku i przed nim jest niewidoczne.
     */
    public function imageOf(Scenario $scenario): Imagick
    {
        $canvas = $this->drawOnly($scenario);
        $this->encoder->quantizeCanvas($canvas, $this->encoder->canvasCarriesBitmap());

        return $canvas;
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
