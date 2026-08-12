<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Diagnostics;

use LightManager\Infrastructure\Diagnostics\BenchmarkArguments;
use LightManager\Infrastructure\Diagnostics\BenchmarkMode;
use LightManager\Infrastructure\Diagnostics\BenchmarkOptions;
use LightManager\Infrastructure\Diagnostics\DiagnosticsException;
use LightManager\Infrastructure\Diagnostics\DiagnosticsProblem;
use LightManager\Infrastructure\Diagnostics\Scenario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Kryterium ukończenia kroku 16 mówi: wszystkie osie konfiguracji przestawiane
 * z linii poleceń. Ten test jest tego dowodem — każda oś ma tu swój przypadek.
 */
final class BenchmarkArgumentsTest extends TestCase
{
    /** Brak argumentów odtwarza punkt odniesienia z kroku 13. */
    public function testDefaultsReproduceTheStepThirteenReferencePoint(): void
    {
        $arguments = BenchmarkArguments::parse([]);

        self::assertSame(BenchmarkMode::Run, $arguments->mode);
        self::assertSame(1000, $arguments->options->widthPixels);
        self::assertSame(600, $arguments->options->heightPixels);
        self::assertSame(166, $arguments->options->columns);
        self::assertSame(46, $arguments->options->rows);
        self::assertSame(64, $arguments->options->paletteColors);
        self::assertFalse($arguments->options->textAntialias);
        self::assertTrue($arguments->options->strokeAntialias);
        self::assertNull($arguments->options->font);
        self::assertSame(BenchmarkOptions::DEFAULT_ITERATIONS, $arguments->options->iterations);
        self::assertSame(Scenario::all(), $arguments->scenarios);
        self::assertFalse($arguments->measureTransfer);
        self::assertFalse($arguments->save);
        self::assertFalse($arguments->compare);
    }

    public function testCanvasSizeCanBeGivenAsOneArgumentOrTwo(): void
    {
        $shortcut = BenchmarkArguments::parse(['--size=800x480'])->options;
        $separate = BenchmarkArguments::parse(['--width=800', '--height=480'])->options;

        self::assertSame(800, $shortcut->widthPixels);
        self::assertSame(480, $shortcut->heightPixels);
        self::assertSame($shortcut->signature(), $separate->signature());
    }

    public function testCharacterGridIsAnAxisIndependentOfCanvasSize(): void
    {
        $options = BenchmarkArguments::parse(['--grid=100x30'])->options;

        self::assertSame(100, $options->columns);
        self::assertSame(30, $options->rows);
        self::assertSame(1000, $options->widthPixels);
    }

    #[DataProvider('axes')]
    public function testEveryConfigurationAxisIsReachable(string $argument, string $expectedInSignature): void
    {
        self::assertStringContainsString(
            $expectedInSignature,
            BenchmarkArguments::parse([$argument])->options->signature(),
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function axes(): iterable
    {
        yield 'paleta' => ['--palette=256', 'palette=256'];

        yield 'wygładzanie tekstu' => ['--text-aa', 'textAA=1'];

        yield 'wygładzanie obrysów wyłączone' => ['--stroke-aa=0', 'strokeAA=0'];

        yield 'motyw' => ['--theme=papier', 'theme=papier'];

        yield 'font' => ['--font=Liberation-Mono', 'font=Liberation-Mono'];
    }

    #[DataProvider('flagValues')]
    public function testFlagsAcceptBothWordsAndDigits(string $value, bool $expected): void
    {
        self::assertSame($expected, BenchmarkArguments::parse(['--text-aa=' . $value])->options->textAntialias);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function flagValues(): iterable
    {
        yield 'jedynka' => ['1', true];

        yield 'zero' => ['0', false];

        yield 'tak' => ['tak', true];

        yield 'no' => ['no', false];

        yield 'TRUE wielkimi literami' => ['TRUE', true];
    }

    public function testScenarioListNarrowsTheRun(): void
    {
        $arguments = BenchmarkArguments::parse(['--scenarios=text,chrome']);

        self::assertSame([Scenario::Text, Scenario::Chrome], $arguments->scenarios);
    }

    public function testIterationsAndWarmupAreSeparateAxes(): void
    {
        $options = BenchmarkArguments::parse(['--iterations=30', '--warmup=0'])->options;

        self::assertSame(30, $options->iterations);
        self::assertSame(0, $options->warmupIterations);
    }

    public function testSaveAndCompareWorkWithAndWithoutAValue(): void
    {
        $bare = BenchmarkArguments::parse(['--save', '--compare']);

        self::assertTrue($bare->save);
        self::assertSame('render', $bare->saveName);
        self::assertTrue($bare->compare);
        self::assertSame('', $bare->comparePath, 'Pusta ścieżka znaczy „najnowszy wzorzec”.');

        $named = BenchmarkArguments::parse(['--save=po-optymalizacji', '--compare=docs/pomiary/stary.json']);

        self::assertSame('po-optymalizacji', $named->saveName);
        self::assertSame('docs/pomiary/stary.json', $named->comparePath);
    }

    public function testPngArgumentSwitchesTheMode(): void
    {
        $arguments = BenchmarkArguments::parse(['--png=/tmp/klatka.png', '--scenario=thumbnail']);

        self::assertSame(BenchmarkMode::Snapshot, $arguments->mode);
        self::assertSame('/tmp/klatka.png', $arguments->pngPath);
        self::assertSame(Scenario::Thumbnail, $arguments->pngScenario);
    }

    public function testHelpWins(): void
    {
        self::assertSame(BenchmarkMode::Help, BenchmarkArguments::parse(['--help'])->mode);
        self::assertSame(BenchmarkMode::Help, BenchmarkArguments::parse(['-h'])->mode);
    }

    /**
     * Literówka w nazwie opcji musi zatrzymać pomiar. Po cichu pominięta
     * mierzyłaby coś innego, niż o co poproszono, i nikt by tego nie zauważył.
     */
    #[DataProvider('rejectedArguments')]
    public function testBadArgumentsAreRejected(string $argument, DiagnosticsProblem $expected): void
    {
        try {
            BenchmarkArguments::parse([$argument]);
            self::fail(sprintf('Argument "%s" powinien zostać odrzucony.', $argument));
        } catch (DiagnosticsException $exception) {
            self::assertSame($expected, $exception->problem);
            self::assertSame($argument, $exception->detail);
        }
    }

    /** @return iterable<string, array{string, DiagnosticsProblem}> */
    public static function rejectedArguments(): iterable
    {
        yield 'nieznana opcja' => ['--nieznana', DiagnosticsProblem::InvalidArgument];

        yield 'literówka w nazwie' => ['--palete=64', DiagnosticsProblem::InvalidArgument];

        yield 'wartość nieliczbowa' => ['--palette=dużo', DiagnosticsProblem::InvalidArgument];

        yield 'zero tam, gdzie potrzeba liczby dodatniej' => ['--width=0', DiagnosticsProblem::InvalidArgument];

        yield 'rozmiar bez drugiego wymiaru' => ['--size=800', DiagnosticsProblem::InvalidArgument];

        yield 'pusta wartość' => ['--theme=', DiagnosticsProblem::InvalidArgument];

        yield 'nierozpoznana wartość przełącznika' => ['--text-aa=może', DiagnosticsProblem::InvalidArgument];
    }

    public function testUnknownScenarioNameIsReportedWithItsName(): void
    {
        try {
            BenchmarkArguments::parse(['--scenarios=text,nieistnieje']);
            self::fail('Nieznany scenariusz powinien zostać odrzucony.');
        } catch (DiagnosticsException $exception) {
            self::assertSame(DiagnosticsProblem::UnknownScenario, $exception->problem);
            self::assertSame('nieistnieje', $exception->detail);
        }
    }

    /** Późniejszy argument wygrywa — inaczej nie dałoby się nadpisać wartości ze skryptu. */
    public function testLaterArgumentOverridesEarlierOne(): void
    {
        self::assertSame(128, BenchmarkArguments::parse(['--palette=16', '--palette=128'])->options->paletteColors);
    }

    /** Tor okienkowy jest osobną osią i domyślnie wyłączony (krok 35). */
    public function testWindowedPathIsOptedIntoExplicitly(): void
    {
        self::assertFalse(BenchmarkArguments::parse([])->options->windowed);
        self::assertTrue(BenchmarkArguments::parse(['--window'])->options->windowed);
        self::assertFalse(BenchmarkArguments::parse(['--window=0'])->options->windowed);
    }

    /**
     * Podpis konfiguracji rozróżnia tory, więc wzorzec okienkowy nie ma jak
     * zostać porównany z sixelowym — a to jedyne zabezpieczenie przed
     * przeczytaniem dwóch różnych pomiarów jako jednego szeregu.
     */
    public function testWindowedSignatureDiffersFromTheTerminalOne(): void
    {
        self::assertNotSame(
            BenchmarkArguments::parse([])->options->signature(),
            BenchmarkArguments::parse(['--window'])->options->signature(),
        );
    }

    /**
     * Przesył mierzy potok Sixela, a zrzut PNG bierze płótno Imagicka — w oknie
     * nie istnieje ani jedno, ani drugie. Głośna odmowa zamiast cichego
     * pominięcia, jak przy każdej literówce w nazwie opcji.
     */
    public function testWindowedPathRefusesTerminalOnlyModes(): void
    {
        foreach ([['--window', '--transfer'], ['--window', '--png=/tmp/x.png']] as $argv) {
            try {
                BenchmarkArguments::parse($argv);
                self::fail('Tor okienkowy powinien odrzucić: ' . implode(' ', $argv));
            } catch (DiagnosticsException $exception) {
                self::assertSame(DiagnosticsProblem::InvalidArgument, $exception->problem);
            }
        }
    }
}
