<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Infrastructure\Glfw\GlfwWindowService;
use LightManager\Infrastructure\Process\BackgroundProcessService;
use LightManager\Infrastructure\Rendering\OpenGlFrameRenderer;
use LightManager\Infrastructure\Rendering\RenderingOptions;

/**
 * Tor okienkowy pomiaru (krok 35, D54): te same scenariusze, ukryte okno GLFW
 * i renderer OpenGL zamiast potoku Sixela.
 *
 * Instrumentacja stoi w całości tutaj, między dwoma publicznymi krokami
 * renderera (`drawFrame()` → `present()`) — ten sam wzorzec, którym D28
 * rozcięła potok sixelowy. Fazy tabeli mapują się tak: „rysowanie” to
 * tłumaczenie klatki na wywołania wektorowe wraz z `endFrame()`, „kodowanie”
 * to zamiana buforów (czyli czekanie na GPU), a „kwantyzacja” jest zerem —
 * palety w tym torze nie ma i zero w kolumnie mówi to wprost. Bajtów też
 * nie ma: klatka nie opuszcza procesu.
 *
 * Metodyka po staremu: rozgrzewka przed pomiarem (pierwsza klatka płaci za
 * atlas glifów i tekstury), jedna instancja renderera na cały przebieg,
 * proces towarzyszący scenariusza `background` doglądany w czasie klatki.
 */
final class WindowBenchmarkRunner
{
    private const COMPANION_SECONDS = 300;

    private readonly BackgroundProcessPort $processes;

    public function __construct(
        private readonly OpenGlFrameRenderer $renderer,
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
        // Okno zostaje ukryte przez cały pomiar; rozmiar ustawia oś `--size`,
        // a `glfwPollEvents()` doręcza zmianę, zanim spadnie pierwsza klatka.
        GlfwWindowService::getInstance()->resizeContent(
            $this->options->widthPixels,
            $this->options->heightPixels,
        );
        glfwPollEvents();

        $results = [];

        foreach ($scenarios as $scenario) {
            $results[] = $this->runOne($scenario);
        }

        return $results;
    }

    private function runOne(Scenario $scenario): ScenarioResult
    {
        $prepared = $this->factory->build($scenario);
        $work = $scenario->needsBackgroundWork()
            ? $this->processes->start('sleep ' . self::COMPANION_SECONDS, self::COMPANION_SECONDS)
            : null;

        try {
            for ($index = 0; $index < $this->options->warmupIterations; ++$index) {
                $this->sample($prepared, $work);
            }

            $samples = [];

            for ($index = 0; $index < max(1, $this->options->iterations); ++$index) {
                $samples[] = $this->sample($prepared, $work);
            }
        } finally {
            if ($work !== null) {
                $this->processes->stop($work);
            }
        }

        return ScenarioResult::fromSamples($scenario, $samples);
    }

    private function sample(ScenarioFrame $prepared, ?BackgroundHandle $work = null): PhaseSample
    {
        $started = microtime(true);

        if ($work !== null) {
            $this->processes->poll($work);
        }

        $this->renderer->drawFrame(
            $prepared->frame,
            $this->rendering,
            $prepared->rows,
            $prepared->columns,
        );

        $drawn = microtime(true);

        $this->renderer->present();

        // **Bez tej linii pomiar kłamie.** OpenGL jest asynchroniczny: bez
        // vsync `glfwSwapBuffers()` wraca, gdy polecenia trafią do kolejki,
        // a nie gdy GPU je wykona — zegar zmierzyłby wtedy koszt *zlecenia*
        // klatki (dziesiąte części milisekundy) i podał go jako koszt klatki.
        // `glFinish()` czeka na sterownik, więc faza „Bufory” niesie
        // prawdziwe czekanie na obraz. W aplikacji tej bariery nie ma i mieć
        // nie powinna — tam asynchroniczność jest zyskiem, nie kłamstwem.
        glFinish();

        $presented = microtime(true);

        return new PhaseSample(
            ($drawn - $started) * 1000,
            0.0,
            ($presented - $drawn) * 1000,
            0,
        );
    }
}
