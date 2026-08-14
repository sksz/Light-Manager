<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Port\TranslatorPort;

/**
 * Zamienia wynik przebiegu na tabelę do wypisania na wyjściu.
 *
 * Napisy idą przez katalog jak w całej aplikacji — inaczej niż **treść** samych
 * mierzonych klatek, która musi zostać nietłumaczona, bo jej długość jest
 * częścią pomiaru (`ScenarioFactory`).
 *
 * Każdy wiersz niesie medianę razem z rozrzutem, bo sama mediana nie mówi, czy
 * wolno jej wierzyć. Wiersz oznaczony jako niestabilny dostaje znacznik przy
 * rozrzucie i nie ma prawa zostać przeczytany jako fakt.
 */
final class ReportTable
{
    private const UNSTABLE_MARKER = '!';

    /** Wielkość, której przebieg nie dostarczył — zero byłoby tu kłamstwem. */
    private const NOT_MEASURED = '—';

    private const COLUMN_GAP = '  ';

    public function __construct(
        private readonly TranslatorPort $translator,
    ) {
    }

    public function render(BenchmarkReport $report): string
    {
        $sections = [
            $this->metadata($report),
            $this->scenarioTable($report),
            $this->transfer($report->transfer),
        ];

        return implode("\n", array_filter($sections, static fn (string $part): bool => $part !== ''));
    }

    private function metadata(BenchmarkReport $report): string
    {
        $environment = $report->environment;

        return implode("\n", [
            $this->translator->translate($report->options->track->reportTitleKey()),
            '',
            $this->translator->translate('bench.report.config', ['config' => $report->options->signature()]),
            $this->translator->translate('bench.report.environment', [
                'php' => $environment->phpVersion,
                'imagick' => $environment->imageMagickVersion,
                'font' => $environment->font,
            ]),
            $this->load($environment),
            $this->translator->translate('bench.report.iterations', [
                'iterations' => $report->options->iterations,
                'warmup' => $report->options->warmupIterations,
            ]),
            '',
        ]);
    }

    /**
     * Wiersz obciążenia maszyny (krok 38).
     *
     * Stoi w metryczce, a nie w przypisie, bo to **on** unieważnił pomiary
     * kroków 16 i 22: różnica środowiska udawała wtedy różnicę kodu przez cztery
     * przebiegi z rzędu.
     */
    private function load(EnvironmentMetadata $environment): string
    {
        if ($environment->loadPerCore === null) {
            return $this->translator->translate('bench.report.loadUnknown');
        }

        return $this->translator->translate(
            $environment->isNoisy() ? 'bench.report.loadNoisy' : 'bench.report.load',
            ['load' => $this->translator->number($environment->loadPerCore, 2)],
        );
    }

    /**
     * Każdy tor ma inne fazy, więc i inne kolumny — a kolumna zer nie jest
     * wynikiem pomiaru, tylko zaśmieceniem tabeli.
     *
     * Tor okienkowy (krok 35) nie ma kwantyzacji (nie ma palety) ani bajtów
     * (klatka nie opuszcza procesu), a to, co gdzie indziej jest kodowaniem,
     * jest w nim zamianą buforów. Tor tekstowy (krok 38) nie ma kwantyzacji,
     * ale bajty ma najprawdziwsze: to one lecą do terminala.
     */
    private function scenarioTable(BenchmarkReport $report): string
    {
        $track = $report->options->track;

        $header = [
            $this->translator->translate('bench.column.scenario'),
            $this->translator->translate($track->firstPhaseLabelKey()),
        ];

        if ($track->hasMiddlePhase()) {
            $header[] = $this->translator->translate($track->middlePhaseLabelKey());
        }

        $header[] = $this->translator->translate($track->lastPhaseLabelKey());
        $header[] = $this->translator->translate('bench.column.total');
        $header[] = $this->translator->translate('bench.column.cold');
        $header[] = $this->translator->translate('bench.column.spread');

        if ($track->producesBlob()) {
            $header[] = $this->translator->translate($track->blobColumnLabelKey());
        }

        $header[] = $this->translator->translate('bench.column.memory');

        $rows = [$header];

        foreach ($report->results as $result) {
            $row = [
                $this->translator->translate($result->scenario->labelKey()),
                $this->phase($result->draw, $result->total),
            ];

            if ($track->hasMiddlePhase()) {
                $row[] = $this->phase($result->quantize, $result->total);
            }

            $row[] = $this->phase($result->encode, $result->total);
            $row[] = $this->milliseconds($result->total->median);
            $row[] = $result->cold === null
                ? self::NOT_MEASURED
                : $this->milliseconds($result->cold->totalMilliseconds());
            $row[] = $this->spread($result);

            if ($track->producesBlob()) {
                $row[] = $track->blobIsBytes()
                    ? $this->kilobytes($result->blobBytes->median)
                    : $this->translator->number($result->blobBytes->median, 0);
            }

            $row[] = $this->megabytes($result->peakMemoryBytes);
            $rows[] = $row;
        }

        // Nazwa scenariusza do lewej, liczby do prawej — jak od kroku 16.
        $alignment = array_fill(0, count($header), true);
        $alignment[0] = false;

        $table = $this->layOut($rows, $alignment);

        if ($report->hasUnstableResults()) {
            $table .= "\n" . $this->translator->translate('bench.report.unstableNote', [
                'ratio' => $this->translator->number(Measurement::UNSTABLE_SPREAD_RATIO, 2),
            ]) . "\n";
        }

        // Kolumna zimnej klatki bez wyjaśnienia byłaby myląca: liczba jest
        // pojedynczą próbką, nie medianą, i mówi o zimnych pamięciach
        // podręcznych klatki, a nie o zimnym procesie (krok 38, D64).
        return $table . "\n" . $this->translator->translate('bench.report.coldNote') . "\n";
    }

    /** Faza z jej udziałem w klatce — bez udziału liczby nie układają się w obraz. */
    private function phase(Measurement $phase, Measurement $total): string
    {
        return sprintf(
            '%s (%s%%)',
            $this->translator->number($phase->median, 1),
            $this->translator->number($phase->shareOf($total), 0),
        );
    }

    private function spread(ScenarioResult $result): string
    {
        $spread = sprintf(
            '%s–%s',
            $this->translator->number($result->total->minimum, 1),
            $this->translator->number($result->total->maximum, 1),
        );

        return $result->isUnstable() ? $spread . ' ' . self::UNSTABLE_MARKER : $spread;
    }

    private function transfer(?TransferResult $transfer): string
    {
        if ($transfer === null) {
            return '';
        }

        $lines = [
            '',
            $this->translator->translate('bench.transfer.title'),
            '',
            $this->translator->translate('bench.transfer.blob', [
                'kilobytes' => $this->translator->number($transfer->blobBytes / 1024.0, 1),
            ]),
            $this->translator->translate('bench.transfer.write', [
                'milliseconds' => $this->translator->number($transfer->writeMilliseconds->median, 1),
                'minimum' => $this->translator->number($transfer->writeMilliseconds->minimum, 1),
                'maximum' => $this->translator->number($transfer->writeMilliseconds->maximum, 1),
            ]),
            $this->translator->translate('bench.transfer.chunks', [
                'chunks' => $this->translator->number($transfer->chunks->median, 1),
            ]),
            $this->translator->translate('bench.transfer.throughput', [
                'throughput' => $this->translator->number($transfer->throughputKilobytesPerSecond(), 0),
            ]),
        ];

        $lines[] = $transfer->roundTripMilliseconds === null
            ? $this->translator->translate('bench.transfer.roundTripMissing')
            : $this->translator->translate('bench.transfer.roundTrip', [
                'milliseconds' => $this->translator->number($transfer->roundTripMilliseconds->median, 1),
            ]);

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param list<ComparisonRow> $rows
     * @param ?EnvironmentMetadata $baselineEnvironment metryczka wzorca — dla
     *                                                  zestawienia obciążeń
     */
    public function renderComparison(
        array $rows,
        string $baselinePath,
        float $thresholdPercent,
        ?EnvironmentMetadata $baselineEnvironment = null,
        ?EnvironmentMetadata $currentEnvironment = null,
    ): string {
        $table = [[
            $this->translator->translate('bench.column.scenario'),
            $this->translator->translate('bench.compare.baseline'),
            $this->translator->translate('bench.compare.current'),
            $this->translator->translate('bench.compare.change'),
        ]];

        foreach ($rows as $row) {
            $table[] = [
                $this->translator->translate($row->scenario->labelKey()),
                $row->baselineMilliseconds === null
                    ? '—'
                    : $this->milliseconds($row->baselineMilliseconds),
                $this->milliseconds($row->currentMilliseconds),
                $this->change($row, $thresholdPercent),
            ];
        }

        $regressions = BaselineComparison::regressions($rows, $thresholdPercent);

        return implode("\n", array_filter([
            '',
            $this->translator->translate('bench.compare.title', ['file' => basename($baselinePath)]),
            '',
            $this->comparedLoads($baselineEnvironment, $currentEnvironment),
            $this->layOut($table, [false, true, true, true]),
            $regressions === []
                ? $this->translator->translate('bench.compare.clean')
                : $this->translator->plural('bench.compare.regressions', count($regressions)),
            '',
        ], static fn (string $part): bool => $part !== ''));
    }

    /**
     * Obciążenie obu maszyn obok siebie — pusty napis, gdy któregoś nie znamy.
     *
     * Dwa przebiegi przy różnym obciążeniu **nie są porównaniem kodu** i to
     * zdanie ma stać nad tabelą, a nie w cudzej pamięci: para wzorców z kroków
     * 21 i 22 „potaniała” o 8–18% bez jednej zmiany w kodzie.
     */
    private function comparedLoads(?EnvironmentMetadata $baseline, ?EnvironmentMetadata $current): string
    {
        if ($baseline?->loadPerCore === null || $current?->loadPerCore === null) {
            return '';
        }

        return $this->translator->translate('bench.compare.load', [
            'baseline' => $this->translator->number($baseline->loadPerCore, 2),
            'current' => $this->translator->number($current->loadPerCore, 2),
        ]) . "\n";
    }

    /**
     * Tabela porównania zrzutów (krok 38): scenariusz, liczba różniących się
     * pikseli i werdykt.
     *
     * Przy niezgodności wypisujemy **ścieżkę obrazu różnicy**, a nie opis, co
     * się zmieniło: narzędzie ma odmówić i wskazać plik, oglądanie zostaje
     * człowiekowi.
     *
     * @param list<SnapshotDifference> $differences
     */
    public function renderImageComparison(array $differences, float $thresholdPerMille): string
    {
        $table = [[
            $this->translator->translate('bench.column.scenario'),
            $this->translator->translate('bench.image.column.pixels'),
            $this->translator->translate('bench.image.column.share'),
            $this->translator->translate('bench.image.column.verdict'),
        ]];
        $files = [];

        foreach ($differences as $difference) {
            $table[] = [
                $this->translator->translate($difference->scenario->labelKey()),
                $difference->verdict === SnapshotVerdict::Match || $difference->verdict === SnapshotVerdict::Differs
                    ? $this->translator->number($difference->differingPixels, 0)
                    : self::NOT_MEASURED,
                $difference->totalPixels === 0
                    ? self::NOT_MEASURED
                    : $this->translator->number($difference->perMille(), 2) . ' ‰',
                $this->translator->translate($difference->verdict->labelKey()),
            ];

            $file = $difference->differencePath ?? ($difference->verdict === SnapshotVerdict::Missing
                ? $difference->baselinePath
                : null);

            if ($file !== null) {
                $files[] = '  ' . $file;
            }
        }

        return implode("\n", array_filter([
            '',
            $this->translator->translate('bench.image.title', [
                'threshold' => $this->translator->number($thresholdPerMille, 2),
            ]),
            '',
            $this->layOut($table, [false, true, true, false]),
            $files === [] ? '' : implode("\n", $files) . "\n",
        ], static fn (string $part): bool => $part !== ''));
    }

    private function change(ComparisonRow $row, float $thresholdPercent): string
    {
        $change = $row->changePercent();

        if ($change === null) {
            return '—';
        }

        $text = sprintf('%s%s%%', $change > 0 ? '+' : '', $this->translator->number($change, 1));

        return match (true) {
            $row->unstable => $text . ' ' . self::UNSTABLE_MARKER,
            $row->isRegression($thresholdPercent) => $text . ' ▲',
            $row->isImprovement($thresholdPercent) => $text . ' ▼',
            default => $text,
        };
    }

    private function milliseconds(float $value): string
    {
        return $this->translator->number($value, 1) . ' ms';
    }

    private function kilobytes(float $bytes): string
    {
        return $this->translator->number($bytes / 1024.0, 1) . ' kB';
    }

    /** Szczyt pamięci w megabajtach — w bajtach byłaby to kolumna nie do objęcia okiem. */
    private function megabytes(int $bytes): string
    {
        return $this->translator->number($bytes / 1048576.0, 1) . ' MB';
    }

    /**
     * Kolumny wyrównane do najszerszej komórki. Liczby idą do prawej, nazwy do
     * lewej — inaczej przecinki dziesiętne nie stoją w jednej linii i tabela
     * przestaje się czytać jednym rzutem oka.
     *
     * @param list<list<string>> $rows
     * @param list<bool>         $alignRight
     */
    private function layOut(array $rows, array $alignRight): string
    {
        $widths = [];

        foreach ($rows as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, mb_strlen($cell));
            }
        }

        $lines = [];

        foreach ($rows as $rowIndex => $row) {
            $cells = [];

            foreach ($row as $index => $cell) {
                $width = $widths[$index] ?? mb_strlen($cell);
                $cells[] = ($alignRight[$index] ?? false)
                    ? mb_str_pad($cell, $width, ' ', STR_PAD_LEFT)
                    : mb_str_pad($cell, $width);
            }

            $lines[] = rtrim(implode(self::COLUMN_GAP, $cells));

            if ($rowIndex === 0) {
                $lines[] = str_repeat('─', min(120, mb_strlen($lines[0])));
            }
        }

        return implode("\n", $lines) . "\n";
    }
}
