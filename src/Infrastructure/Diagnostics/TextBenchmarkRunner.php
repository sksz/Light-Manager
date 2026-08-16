<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Infrastructure\Rendering\RenderingOptions;
use LightManager\Infrastructure\Rendering\TextFrameRenderer;

/**
 * Tor tekstowy pomiaru (krok 38): te same scenariusze przepuszczone przez
 * renderer ANSI — trzeciego tłumacza słownika prymitywów.
 *
 * Do tego kroku tryb zapasowy był **jedynym z trzech, o którego koszcie nikt
 * nic nie wiedział**: `PrimitiveTranslationTableTest` pilnował, żeby każdy
 * kształt miał w nim tłumaczenie, ale żaden pomiar nie mówił, ile to
 * tłumaczenie kosztuje — więc regresja w trybie, który włącza się dokładnie
 * wtedy, gdy nic innego nie działa, była niewidzialna.
 *
 * Fazy tabeli mapują się tak: „rysowanie” to tłumaczenie prymitywów na bufor
 * komórek, „kodowanie” to złożenie bajtów ANSI wraz z sekwencjami sterującymi,
 * a „kwantyzacja” jest zerem — palety w tym torze nie ma i zero w kolumnie mówi
 * to wprost, jak w torze okienkowym. Blob jest tu prawdziwy i wart czytania:
 * to dokładnie te bajty, które trafiają do terminala.
 */
final class TextBenchmarkRunner extends AbstractBenchmarkRunner
{
    public function __construct(
        private readonly TextFrameRenderer $renderer,
        ScenarioFactory $factory,
        BenchmarkOptions $options,
        private readonly RenderingOptions $rendering,
        ?BackgroundProcessPort $processes = null,
    ) {
        parent::__construct($factory, $options, $processes);
    }

    protected function sample(ScenarioFrame $prepared): PhaseSample
    {
        $started = microtime(true);

        $this->advanceCompanions();

        $buffer = $this->renderer->composeBuffer(
            $prepared->frame,
            $this->rendering->theme,
            $prepared->rows,
            $prepared->columns,
        );

        $composed = microtime(true);

        $bytes = $this->renderer->encode($buffer);
        $encoded = microtime(true);

        return new PhaseSample(
            ($composed - $started) * 1000,
            0.0,
            ($encoded - $composed) * 1000,
            strlen($bytes),
        );
    }

    /** Gotowe bajty ANSI jednego scenariusza — wejście do pomiaru przesyłu. */
    public function blobFor(Scenario $scenario): string
    {
        $prepared = $this->factory->build($scenario);

        return $this->renderer->encode($this->renderer->composeBuffer(
            $prepared->frame,
            $this->rendering->theme,
            $prepared->rows,
            $prepared->columns,
        ));
    }
}
