<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Diagnostics;

use LightManager\Infrastructure\Diagnostics\BenchmarkOptions;
use LightManager\Infrastructure\Diagnostics\BenchmarkTrack;
use LightManager\Infrastructure\Diagnostics\LoopBenchmarkRunner;
use LightManager\Infrastructure\Diagnostics\Scenario;
use LightManager\Infrastructure\Diagnostics\ScenarioFactory;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Tor taktu pętli mierzy drogę od klawisza do prymitywów — więc test pilnuje
 * rzeczy, której sam pomiar nie pokaże: że ta droga **naprawdę się odbywa**.
 * Przebieg, w którym nic się nie składa, też podałby liczby.
 */
final class LoopBenchmarkRunnerTest extends TestCase
{
    public function testTickComposesARealFrame(): void
    {
        $options = new BenchmarkOptions(iterations: 2, warmupIterations: 1, track: BenchmarkTrack::Loop);
        $runner = new LoopBenchmarkRunner(
            new ScenarioFactory($options),
            $options,
            new StubTranslator(),
        );

        $results = $runner->run([Scenario::ChromeWithText]);

        self::assertCount(1, $results);
        // Kolumna „Prymitywy” zamiast bajtów: klatka nie opuszcza tu procesu,
        // ale ma objętość — i ta objętość musi być większa od zera.
        self::assertGreaterThan(0, $results[0]->blobBytes->median);
    }

    /** Zimna klatka bierze się z rozgrzewki także tutaj — pierwszy takt płaci najwięcej. */
    public function testFirstTickIsReportedAsTheColdOne(): void
    {
        $options = new BenchmarkOptions(iterations: 2, warmupIterations: 1, track: BenchmarkTrack::Loop);
        $runner = new LoopBenchmarkRunner(new ScenarioFactory($options), $options, new StubTranslator());

        $result = $runner->run([Scenario::ChromeWithText])[0];

        self::assertNotNull($result->cold);
        self::assertGreaterThan(0.0, $result->cold->totalMilliseconds());
    }
}
