<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use GL\Buffer\UByteBuffer;
use Imagick;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Infrastructure\Glfw\GlfwWindowService;
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
 * Metodyka po staremu i wspólna z pozostałymi torami (`AbstractBenchmarkRunner`):
 * rozgrzewka przed pomiarem, pierwsza jej próbka raportowana jako zimna klatka
 * — tu płaci ona za **atlas glifów i tekstury podglądów**, a nie za bitmapy
 * wierszy — jedna instancja renderera na cały przebieg i proces towarzyszący
 * scenariusza `background` doglądany w czasie klatki.
 */
final class WindowBenchmarkRunner extends AbstractBenchmarkRunner implements ScenarioImageSource
{
    public function __construct(
        private readonly OpenGlFrameRenderer $renderer,
        ScenarioFactory $factory,
        BenchmarkOptions $options,
        private readonly RenderingOptions $rendering,
        ?BackgroundProcessPort $processes = null,
    ) {
        parent::__construct($factory, $options, $processes);
    }

    /**
     * Okno zostaje ukryte przez cały pomiar; rozmiar ustawia oś `--size`,
     * a `glfwPollEvents()` doręcza zmianę, zanim spadnie pierwsza klatka.
     */
    protected function prepareRun(): void
    {
        GlfwWindowService::getInstance()->resizeContent(
            $this->options->widthPixels,
            $this->options->heightPixels,
        );
        glfwPollEvents();
    }

    /**
     * Zrzut klatki **prosto z bufora GPU** (krok 38) — czyli to, co naprawdę
     * narysowała karta, a nie to, co narysowałby na jej miejscu Imagick.
     *
     * Dwie rzeczy są tu obowiązkowe i obie wynikają z tego, jak działa OpenGL.
     * `glFinish()` przed odczytem, bo bez niego czytalibyśmy bufor, do którego
     * sterownik jeszcze nie skończył rysować. I **odwrócenie w pionie**, bo
     * początek układu współrzędnych OpenGL leży w lewym dolnym rogu, a obrazu —
     * w lewym górnym; bez tego wzorzec byłby klatką do góry nogami.
     */
    public function imageOf(Scenario $scenario): Imagick
    {
        $this->prepareRun();
        $prepared = $this->factory->build($scenario);

        $this->renderer->drawFrame($prepared->frame, $this->rendering, $prepared->rows, $prepared->columns);
        $this->renderer->present();
        glFinish();

        ['width' => $width, 'height' => $height] = GlfwWindowService::getInstance()->framebufferSize();
        $pixels = new UByteBuffer();
        $pixels->reserve($width * $height * 4);
        glReadPixels(0, 0, $width, $height, GL_RGBA, GL_UNSIGNED_BYTE, $pixels);

        $image = new Imagick();
        $image->readImageBlob($this->portableHeader($width, $height) . $pixels->dump());
        $image->flipImage();

        return $image;
    }

    /**
     * Nagłówek PAM (`P7`) doklejany przed surowe bajty z karty.
     *
     * Bez niego trzeba by ImagickowI wyjaśniać rozmiar i głębię osobnymi
     * wywołaniami, a przy budowie Q16 „osiem bitów na kanał” nie jest wartością
     * domyślną — obraz wyszedłby przemieszany. `MAXVAL 255` mówi to wprost
     * i jest odporne na wersję biblioteki.
     */
    private function portableHeader(int $width, int $height): string
    {
        return sprintf(
            "P7\nWIDTH %d\nHEIGHT %d\nDEPTH 4\nMAXVAL 255\nTUPLTYPE RGB_ALPHA\nENDHDR\n",
            $width,
            $height,
        );
    }

    protected function sample(ScenarioFrame $prepared): PhaseSample
    {
        $started = microtime(true);

        $this->advanceCompanions();

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
