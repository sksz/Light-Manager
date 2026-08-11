<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use Imagick;
use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Infrastructure\Imagick\SixelFrameEncoder;
use LightManager\Infrastructure\Process\BackgroundProcessService;
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
    /**
     * Ile sekund ma przeżyć proces towarzyszący scenariuszowi `background`.
     *
     * Pięć minut z zapasem starcza na przebieg o stu powtórzeniach w dużym oknie,
     * a jednocześnie jest liczbą skończoną: gdyby narzędzie padło w sposób, którego
     * `finally` nie łapie, potomek zniknie sam.
     */
    private const COMPANION_SECONDS = 300;

    private readonly BackgroundProcessPort $processes;

    public function __construct(
        private readonly SixelFrameEncoder $encoder,
        private readonly ScenarioFactory $factory,
        private readonly BenchmarkOptions $options,
        private readonly RenderingOptions $rendering,
        ?BackgroundProcessPort $processes = null,
    ) {
        $this->processes = $processes ?? BackgroundProcessService::getInstance();
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
        $work = $this->startBackgroundWork($scenario);

        try {
            for ($index = 0; $index < $this->options->warmupIterations; ++$index) {
                $this->sample($prepared, $work);
            }

            $samples = [];

            for ($index = 0; $index < max(1, $this->options->iterations); ++$index) {
                $samples[] = $this->sample($prepared, $work);
            }
        } finally {
            // `finally`, bo przebieg przerwany w połowie nie ma prawa zostawić
            // po sobie procesu — narzędzie pomiarowe podlega tej samej regule,
            // co aplikacja.
            if ($work !== null) {
                $this->processes->stop($work);
            }
        }

        return ScenarioResult::fromSamples($scenario, $samples);
    }

    /**
     * Proces potomny towarzyszący pomiarowi — albo `null`, gdy scenariusz go nie
     * zamawia.
     *
     * Polecenie **milczy i śpi**, bo tak właśnie zachowuje się `du`: nie mówi
     * o sobie nic, aż skończy. Limit czasu jest hojny z tego samego powodu, dla
     * którego proces w ogóle tu stoi — ma przeżyć cały przebieg, także ten
     * z setką powtórzeń, a gdyby mimo wszystko nie przeżył, pomiar zmierzyłby
     * klatkę bez sąsiada i cicho skłamał.
     */
    private function startBackgroundWork(Scenario $scenario): ?BackgroundHandle
    {
        return $scenario->needsBackgroundWork()
            ? $this->processes->start('sleep ' . self::COMPANION_SECONDS, self::COMPANION_SECONDS)
            : null;
    }

    /**
     * Jeden pełny przebieg potoku z zegarem między fazami.
     *
     * Kolejność i warunki są tu identyczne jak w `SixelFrameEncoder::encode()`,
     * łącznie z pytaniem enkodera o to, czy w klatce wylądowała bitmapa —
     * gdybyśmy zgadywali tę odpowiedź, scenariusz z miniaturą mógłby zostać
     * zmierzony na innej palecie niż ta, której użyje aplikacja.
     */
    private function sample(ScenarioFrame $prepared, ?BackgroundHandle $work = null): PhaseSample
    {
        $started = microtime(true);

        // Doglądanie pracy tłowej **wchodzi do czasu klatki**, bo w aplikacji też
        // do niego wchodzi: ekran pyta o stan raz na klatkę, tuż przed rysowaniem.
        // Doliczone jest do fazy rysowania — osobna faza dla dwóch pustych potoków
        // byłaby kolumną zer w każdym pozostałym scenariuszu.
        if ($work !== null) {
            $this->processes->poll($work);
        }

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
