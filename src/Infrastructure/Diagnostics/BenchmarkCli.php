<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\RendererMode;
use LightManager\Infrastructure\Glfw\GlfwWindowService;
use LightManager\Infrastructure\I18n\TranslatorService;
use LightManager\Infrastructure\Imagick\SixelFrameEncoder;
use LightManager\Infrastructure\Rendering\AnsiPalette;
use LightManager\Infrastructure\Rendering\OpenGlFrameRenderer;
use LightManager\Infrastructure\Rendering\TextFrameRenderer;
use LightManager\Infrastructure\Rendering\Theme;
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
        private readonly ?SnapshotStore $snapshots = null,
        private readonly ?GoldenFrames $golden = null,
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
                BenchmarkMode::ImageSave => $this->saveImages($arguments),
                BenchmarkMode::ImageCompare => $this->compareImages($arguments),
                BenchmarkMode::GoldenSave => $this->saveGoldenFrames($arguments),
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
        $track = $arguments->options->track;

        try {
            $this->progress('bench.progress.running', [
                'scenarios' => count($arguments->scenarios),
                'iterations' => $arguments->options->iterations,
            ]);

            [$results, $transfer] = match ($track) {
                BenchmarkTrack::Window => [
                    $this->windowRunnerFor($arguments, $fixture?->path)->run($arguments->scenarios),
                    null,
                ],
                BenchmarkTrack::Text => $this->measureTerminal(
                    $arguments,
                    $this->textRunnerFor($arguments, $fixture?->path),
                ),
                BenchmarkTrack::Sixel => $this->measureTerminal(
                    $arguments,
                    $this->runnerFor($arguments, $fixture?->path),
                ),
                BenchmarkTrack::Loop => [
                    (new LoopBenchmarkRunner(
                        new ScenarioFactory($arguments->options),
                        $arguments->options,
                        $this->translator,
                    ))->run($arguments->scenarios),
                    null,
                ],
            };

            $report = (new BenchmarkReport(
                $arguments->options,
                EnvironmentMetadata::current($arguments->options->font, $track),
                $results,
            ))->withTransfer($transfer);

            echo $this->table->render($report);

            $this->compare($arguments, $report);
            $this->save($arguments, $report);

            return 0;
        } finally {
            $fixture?->remove();

            $this->closeWindow($track);
        }
    }

    /**
     * Tor terminalowy — sixelowy albo tekstowy: przebieg wraz z fazą przesyłu.
     *
     * Przesył jest jedyną fazą, która potrzebuje prawdziwego terminala, i nie
     * ma odpowiednika w oknie. Od kroku 38 mierzy się go w **obu** torach
     * terminalowych: bajty ANSI też trzeba wypchnąć, a ich koszt jest tak samo
     * prawdziwy jak koszt bloba Sixela.
     *
     * @return array{list<ScenarioResult>, TransferResult|null}
     */
    private function measureTerminal(BenchmarkArguments $arguments, BenchmarkRunner|TextBenchmarkRunner $runner): array
    {
        return [$runner->run($arguments->scenarios), $this->transferFor($arguments, $runner)];
    }

    /**
     * Tor tekstowy (krok 38): renderer ANSI zamiast potoku Sixela.
     *
     * Osi jakości ten tor nie ma — wygładzanie i paleta nie dotyczą siatki
     * znakowej — więc z `RenderingOptions` bierze wyłącznie motyw. Zero
     * w kolumnie kwantyzacji mówi to samo wprost.
     */
    private function textRunnerFor(BenchmarkArguments $arguments, ?string $imagePath): TextBenchmarkRunner
    {
        return new TextBenchmarkRunner(
            new TextFrameRenderer(AnsiPalette::fromEnvironment()),
            new ScenarioFactory($arguments->options, $imagePath),
            $arguments->options,
            $arguments->options->toRenderingOptions($this->themeFor($arguments)),
        );
    }

    /**
     * Tor okienkowy: ukryte okno GLFW i renderer OpenGL zamiast potoku Sixela
     * (krok 35, D54).
     *
     * Okno powstaje ukryte samo z siebie (hint `GLFW_VISIBLE`), więc pomiar
     * niczego nie musi chować — ale i niczego nie pokazuje: `showAtGrid()`
     * woła wyłącznie `Bootstrap` aplikacji.
     */
    private function windowRunnerFor(BenchmarkArguments $arguments, ?string $imagePath): WindowBenchmarkRunner
    {
        if (!extension_loaded('glfw')) {
            throw DiagnosticsException::forUnavailableGlfw();
        }

        return new WindowBenchmarkRunner(
            new OpenGlFrameRenderer(),
            new ScenarioFactory($arguments->options, $imagePath),
            $arguments->options,
            $arguments->options->toRenderingOptions($this->themeFor($arguments)),
        );
    }

    /**
     * Motyw wskazany osią `--theme`. Nazwa spoza katalogu kończy się odmową,
     * a nie cichym powrotem do motywu domyślnego: pomiar szedłby wtedy na innym
     * motywie, niż zapisano w podpisie konfiguracji.
     */
    private function themeFor(BenchmarkArguments $arguments): Theme
    {
        $themes = ThemeService::getInstance();

        if (!$themes->has($arguments->options->themeName)) {
            throw DiagnosticsException::forUnknownTheme($arguments->options->themeName);
        }

        return $themes->named($arguments->options->themeName);
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

    /**
     * Zapis złotych klatek (krok 38): serializacja prymitywów każdego wybranego
     * scenariusza do `tests/Golden/`.
     *
     * Jedyna droga do regeneracji i **jawna z założenia**: złoty plik odnowiony
     * automatem przestaje być testem, bo zapisuje także zmianę, której nikt nie
     * chciał. Kto to woła, ma najpierw przeczytać różnicę.
     */
    private function saveGoldenFrames(BenchmarkArguments $arguments): int
    {
        $golden = $this->golden ?? GoldenFrames::default();

        foreach ($arguments->scenarios as $scenario) {
            echo $this->translator->translate('bench.golden.saved', [
                'file' => basename($golden->save($scenario)),
            ]) . PHP_EOL;
        }

        return 0;
    }

    /**
     * Zapis wzorcowych zrzutów wybranych scenariuszy (krok 38).
     *
     * Zapisuje **bez pytania**, bo to jawna prośba użytkownika, ale wypisuje
     * każdy plik z osobna: wzorzec nadpisany po cichu jest gorszy od braku
     * wzorca — nikt nie zauważy, że punkt odniesienia właśnie przestał
     * odpowiadać temu, co było przed zmianą.
     */
    private function saveImages(BenchmarkArguments $arguments): int
    {
        $fixture = $this->fixtureFor($arguments);
        $store = $this->snapshots ?? SnapshotStore::default();
        $track = $arguments->options->track;

        try {
            $source = $this->imageSourceFor($arguments, $fixture?->path);

            foreach ($arguments->scenarios as $scenario) {
                $image = $source->imageOf($scenario);

                try {
                    echo $this->translator->translate('bench.image.saved', [
                        'file' => basename(
                            $store->save($image, $track, $scenario, $arguments->options->signature()),
                        ),
                    ]) . PHP_EOL;
                } finally {
                    $image->clear();
                }
            }

            return 0;
        } finally {
            $fixture?->remove();
            $this->closeWindow($track);
        }
    }

    /**
     * Porównanie zrzutów z wzorcami. Kod wyjścia **nie jest kosmetyką**: to on
     * czyni z obrazu miarę, a nie ilustrację.
     */
    private function compareImages(BenchmarkArguments $arguments): int
    {
        $fixture = $this->fixtureFor($arguments);
        $track = $arguments->options->track;
        $threshold = $arguments->imageThreshold();

        try {
            $source = $this->imageSourceFor($arguments, $fixture?->path);
            $comparison = new SnapshotComparison($this->snapshots ?? SnapshotStore::default());
            $differences = [];

            foreach ($arguments->scenarios as $scenario) {
                $image = $source->imageOf($scenario);

                try {
                    $differences[] = $comparison->compare(
                        $image,
                        $track,
                        $scenario,
                        $threshold,
                        $arguments->options->signature(),
                    );
                } finally {
                    $image->clear();
                }
            }

            echo $this->table->renderImageComparison($differences, $threshold);

            foreach ($differences as $difference) {
                if ($difference->verdict->isFailure()) {
                    return 1;
                }
            }

            return 0;
        } finally {
            $fixture?->remove();
            $this->closeWindow($track);
        }
    }

    /**
     * Tor, który potrafi oddać obraz klatki. Tekstowy takim torem nie jest
     * i parser odrzuca go wcześniej — tutaj zostają dwa.
     */
    private function imageSourceFor(BenchmarkArguments $arguments, ?string $imagePath): ScenarioImageSource
    {
        return $arguments->options->track === BenchmarkTrack::Window
            ? $this->windowRunnerFor($arguments, $imagePath)
            : $this->runnerFor($arguments, $imagePath);
    }

    private function closeWindow(BenchmarkTrack $track): void
    {
        if ($track === BenchmarkTrack::Window) {
            GlfwWindowService::getInstance()->close();
        }
    }

    private function runnerFor(BenchmarkArguments $arguments, ?string $imagePath): BenchmarkRunner
    {
        return new BenchmarkRunner(
            new SixelFrameEncoder(),
            new ScenarioFactory($arguments->options, $imagePath),
            $arguments->options,
            $arguments->options->toRenderingOptions($this->themeFor($arguments)),
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
    private function transferFor(
        BenchmarkArguments $arguments,
        BenchmarkRunner|TextBenchmarkRunner $runner,
    ): ?TransferResult {
        if (!$arguments->measureTransfer) {
            return null;
        }

        if (!stream_isatty(STDIN) || !stream_isatty(STDOUT)) {
            $this->progress('bench.transfer.skippedNoTerminal');

            return null;
        }

        $terminal = TerminalService::getInstance();

        // Sixela wymaga wyłącznie tor sixelowy — bajty ANSI wyświetli każdy
        // terminal, więc odmowa z powodu braku DA1 byłaby tam bez sensu.
        if ($arguments->options->track === BenchmarkTrack::Sixel
            && SixelCapabilityService::getInstance()->detect() !== RendererMode::Sixel) {
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
        $path = $arguments->comparePath === ''
            ? $store->newest($arguments->options)
            : $arguments->comparePath;
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
            $baseline->environment,
            $current->environment,
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

        // Obciążenie **ostrzega, ale nie odmawia** (D64): to przesłanka, a nie
        // skutek — rozrzut wewnątrz przebiegu bywa wąski mimo zajętej maszyny,
        // więc odmowa na tej podstawie blokowałaby także dobre wzorce. Decyzję
        // podejmuje człowiek, mając liczbę przed oczami.
        if ($report->environment->isNoisy()) {
            echo $this->translator->translate('bench.save.noisyLoad', [
                'load' => $this->translator->number($report->environment->loadPerCore ?? 0.0, 2),
                'limit' => $this->translator->number(EnvironmentMetadata::NOISY_LOAD_PER_CORE, 2),
            ]) . PHP_EOL;
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
