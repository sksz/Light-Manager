<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Presentation\Cli\FrameComposer;
use LightManager\Presentation\Cli\LoopState;

/**
 * Tor taktu pętli (krok 38): odczyt wejścia → aktualizacja stanu → złożenie
 * klatki, **bez renderera i bez przesyłu**.
 *
 * Odpowiada na pytanie, którego tabela główna nigdy nie zadała: ile kosztuje
 * *złożenie* klatki, zanim ktokolwiek zacznie ją rysować. Szesnaście
 * scenariuszy pomiarowych powstaje w `ScenarioFactory` **wprost z prymitywów**,
 * z pominięciem ekranów, więc `FrameComposer`, `HudLayout`, strefy skrajne
 * i pasek stanu były dotąd poza zasięgiem każdego pomiaru — a wykonują się
 * trzydzieści razy na sekundę.
 *
 * Trzy rzeczy są tu umyślne:
 *
 * - **Klawisz naprawdę zmienia stan.** Wejście podaje na przemian strzałki
 *   w dół i w górę, więc zaznaczenie wędruje i okno przewijania się przelicza.
 *   Takt mierzony na klawiszu, który nic nie robi, byłby kolumną zer.
 * - **Renderer nie istnieje.** `FrameComposer` kończy pracę wywołaniem
 *   `render()`, więc dostaje ujście, które klatkę wyłącznie przyjmuje — inaczej
 *   mierzylibyśmy potok, który ma już swoje trzy tory.
 * - **Systemu plików nie ma.** Treść bierze się z `LoopScenarioScreen`, tak jak
 *   treść klatek z `ScenarioFactory`: z licznika, nie z katalogu na dysku.
 */
final class LoopBenchmarkRunner extends AbstractBenchmarkRunner
{
    private readonly LoopScenarioScreen $screen;

    private readonly LoopState $state;

    private readonly FrameComposer $composer;

    private readonly SinkFrameRenderer $sink;

    private int $tick = 0;

    public function __construct(
        ScenarioFactory $factory,
        BenchmarkOptions $options,
        TranslatorPort $translator,
        ?BackgroundProcessPort $processes = null,
    ) {
        parent::__construct($factory, $options, $processes);

        $this->screen = new LoopScenarioScreen();
        $this->state = new LoopState();
        $this->sink = new SinkFrameRenderer();
        $this->composer = new FrameComposer(
            $this->sink,
            new FixedViewport($options->rows, $options->columns),
            $translator,
        );
    }

    protected function sample(ScenarioFrame $prepared, ?BackgroundHandle $work = null): PhaseSample
    {
        $started = microtime(true);

        $this->pollCompanion($work);

        $key = $this->nextKey();
        $read = microtime(true);

        $this->screen->handle($key);
        $updated = microtime(true);

        $this->composer->render($this->screen, $this->state);
        $composed = microtime(true);

        return new PhaseSample(
            ($read - $started) * 1000,
            ($updated - $read) * 1000,
            ($composed - $updated) * 1000,
            $this->sink->primitiveCount(),
        );
    }

    /**
     * Wejście udawane, ale **nie puste**: strzałki na przemian w dół i w górę.
     *
     * Prawdziwy `InputPort` czyta bajty z terminala albo pyta GLFW o zdarzenia —
     * jedno i drugie jest kosztem systemu, nie kosztem pętli, i nie miałoby jak
     * powtórzyć się identycznie w każdym przebiegu. Faza „wejście” mierzy więc
     * to, co w takcie zostaje po odjęciu systemu: rozpoznanie klawisza jako
     * `KeyPress`.
     */
    private function nextKey(): KeyPress
    {
        $down = $this->tick++ % 2 === 0;

        return new KeyPress($down ? Key::ArrowDown : Key::ArrowUp, $down ? "\e[B" : "\e[A");
    }
}
