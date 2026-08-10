<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\RendererMode;
use LightManager\Infrastructure\I18n\TranslatorService;
use LightManager\Infrastructure\Imagick\SixelFrameEncoder;
use LightManager\Infrastructure\Rendering\ThemeService;
use LightManager\Infrastructure\Terminal\SixelCapabilityService;
use LightManager\Infrastructure\Terminal\TerminalService;

/**
 * Spina narzędzie w całość: argumenty → przebieg → wydruk → wzorzec.
 *
 * Cała logika mieszka tutaj i w sąsiednich klasach, a `bin/render-bench` jest
 * cienkim punktem wejścia — tak samo jak `bin/light-manager` wobec
 * `Presentation\Cli\Bootstrap`. Powód jest praktyczny: `bin/` leży poza PHPStanem
 * i PHP-CS-Fixerem, więc kod, który ma być pilnowany, nie może tam mieszkać.
 *
 * Faza przesyłu jest jedyną, która potrzebuje terminala, i jedyną, której brak
 * jest **raportowany wprost**. Narzędzie nigdy nie podstawia w jej miejsce
 * zapisu do pliku ani do `/dev/null`: zmierzyłoby wtedy prędkość jądra i podało
 * ją jako prędkość terminala.
 */
final class BenchmarkCli
{
    private readonly TranslatorPort $translator;

    private readonly ReportTable $table;

    public function __construct(
        ?TranslatorPort $translator = null,
        private readonly ?BaselineStore $store = null,
    ) {
        $this->translator = $translator ?? TranslatorService::getInstance();
        $this->table = new ReportTable($this->translator);
    }

    /**
     * @param list<string> $argv argumenty bez nazwy programu
     *
     * @return int kod wyjścia procesu
     */
    public function run(array $argv): int
    {
        try {
            $arguments = BenchmarkArguments::parse($argv);

            return match ($arguments->mode) {
                BenchmarkMode::Help => $this->showHelp(),
                BenchmarkMode::Snapshot => $this->writeSnapshot($arguments),
                BenchmarkMode::Run => $this->measure($arguments),
            };
        } catch (DiagnosticsException $exception) {
            fwrite(STDERR, $this->describe($exception) . PHP_EOL);

            return 1;
        }
    }

    private function measure(BenchmarkArguments $arguments): int
    {
        $fixture = $this->fixtureFor($arguments);

        try {
            $runner = $this->runnerFor($arguments, $fixture?->path);

            $this->progress('bench.progress.running', [
                'scenarios' => count($arguments->scenarios),
                'iterations' => $arguments->options->iterations,
            ]);

            $report = new BenchmarkReport(
                $arguments->options,
                EnvironmentMetadata::current($arguments->options->font),
                $runner->run($arguments->scenarios),
            );

            $report = $report->withTransfer($this->transferFor($arguments, $runner));

            echo $this->table->render($report);

            $this->compare($arguments, $report);
            $this->save($arguments, $report);

            return 0;
        } finally {
            $fixture?->remove();
        }
    }

    private function writeSnapshot(BenchmarkArguments $arguments): int
    {
        $fixture = $this->fixtureFor($arguments);

        try {
            $snapshot = new CanvasSnapshot($this->runnerFor($arguments, $fixture?->path));
            $snapshot->write($arguments->pngScenario, $arguments->pngPath);

            echo $this->translator->translate('bench.snapshot.saved', [
                'file' => $arguments->pngPath,
                'scenario' => $this->translator->translate($arguments->pngScenario->labelKey()),
            ]) . PHP_EOL;

            return 0;
        } finally {
            $fixture?->remove();
        }
    }

    private function runnerFor(BenchmarkArguments $arguments, ?string $imagePath): BenchmarkRunner
    {
        $themes = ThemeService::getInstance();

        if (!$themes->has($arguments->options->themeName)) {
            throw DiagnosticsException::forUnknownTheme($arguments->options->themeName);
        }

        return new BenchmarkRunner(
            new SixelFrameEncoder(),
            new ScenarioFactory($arguments->options, $imagePath),
            $arguments->options,
            $arguments->options->toRenderingOptions($themes->named($arguments->options->themeName)),
        );
    }

    /** Obraz powstaje tylko wtedy, gdy któryś scenariusz naprawdę go potrzebuje. */
    private function fixtureFor(BenchmarkArguments $arguments): ?ImageFixture
    {
        $needed = $arguments->mode === BenchmarkMode::Snapshot
            ? $arguments->pngScenario === Scenario::Thumbnail
            : in_array(Scenario::Thumbnail, $arguments->scenarios, true);

        return $needed ? ImageFixture::create() : null;
    }

    /**
     * Faza przesyłu — albo pomiar pod prawdziwym terminalem, albo jawne „nie
     * zmierzono” z podaniem powodu.
     */
    private function transferFor(BenchmarkArguments $arguments, BenchmarkRunner $runner): ?TransferResult
    {
        if (!$arguments->measureTransfer) {
            return null;
        }

        if (!stream_isatty(STDIN) || !stream_isatty(STDOUT)) {
            $this->progress('bench.transfer.skippedNoTerminal');

            return null;
        }

        $terminal = TerminalService::getInstance();

        if (SixelCapabilityService::getInstance()->detect() !== RendererMode::Sixel) {
            $this->progress('bench.transfer.skippedNoSixel');

            return null;
        }

        $blob = $runner->blobFor($this->transferScenario($arguments));

        // Klatki lecą na osobny ekran, żeby obrazy nie zasypały tabeli, którą
        // za chwilę wypiszemy — i żeby po wyjściu powłoka wyglądała jak przedtem.
        $terminal->enterAlternateScreen();

        try {
            return (new TransferMeter($terminal))->measure($blob, $arguments->options->iterations, true);
        } finally {
            $terminal->restore();
        }
    }

    /** Do przesyłu bierzemy najbogatszy z wybranych scenariuszy — blob jest wtedy realistyczny. */
    private function transferScenario(BenchmarkArguments $arguments): Scenario
    {
        foreach ([Scenario::Thumbnail, Scenario::Popup, Scenario::ChromeWithText] as $candidate) {
            if (in_array($candidate, $arguments->scenarios, true)) {
                return $candidate;
            }
        }

        return $arguments->scenarios[0] ?? Scenario::ChromeWithText;
    }

    private function compare(BenchmarkArguments $arguments, BenchmarkReport $report): void
    {
        if (!$arguments->compare) {
            return;
        }

        $store = $this->store ?? BaselineStore::default();
        $path = $arguments->comparePath === '' ? $store->newest() : $arguments->comparePath;
        $baseline = $store->load($path);
        $current = $report->toSnapshot();

        if (!$baseline->isComparableWith($current)) {
            echo $this->translator->translate('bench.compare.incomparable', [
                'baseline' => $baseline->options->signature(),
                'current' => $current->options->signature(),
            ]) . PHP_EOL;

            return;
        }

        echo $this->table->renderComparison(
            BaselineComparison::between($baseline, $current),
            $path,
            $arguments->thresholdPercent,
        );
    }

    private function save(BenchmarkArguments $arguments, BenchmarkReport $report): void
    {
        if (!$arguments->save) {
            return;
        }

        if ($report->hasUnstableResults()) {
            // Wzorzec ma być punktem odniesienia, a nie zapisem tego, co akurat
            // pokazał zegar w chwili, gdy maszyna była czymś zajęta.
            echo $this->translator->translate('bench.save.refusedUnstable') . PHP_EOL;

            return;
        }

        $path = ($this->store ?? BaselineStore::default())->save($report->toSnapshot(), $arguments->saveName);

        echo $this->translator->translate('bench.save.done', ['file' => $path]) . PHP_EOL;
    }

    private function showHelp(): int
    {
        $lines = [
            $this->translator->translate('bench.help.usage'),
            '',
            $this->translator->translate('bench.help.axes'),
            $this->translator->translate('bench.help.modes'),
            '',
            $this->translator->translate('bench.help.scenarios'),
        ];

        foreach (Scenario::all() as $scenario) {
            $lines[] = sprintf(
                '  %-12s %s',
                $scenario->value,
                $this->translator->translate($scenario->labelKey()),
            );
        }

        echo implode(PHP_EOL, $lines) . PHP_EOL;

        return 0;
    }

    /** @param array<string, string|int|float> $parameters */
    private function progress(string $key, array $parameters = []): void
    {
        fwrite(STDERR, $this->translator->translate($key, $parameters) . PHP_EOL);
    }

    /**
     * Napis dla użytkownika dobierany po rodzaju awarii, a konkret brany
     * z typowanego pola wyjątku — ta sama zasada, którą krok 15 wprowadził dla
     * `ProblemPresenter`. Komunikat techniczny zostaje w wyjątku.
     */
    private function describe(DiagnosticsException $exception): string
    {
        return $this->translator->translate(
            $exception->problem->textKey(),
            ['detail' => $exception->detail],
        );
    }
}
