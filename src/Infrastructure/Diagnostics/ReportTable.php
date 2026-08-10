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
            $this->translator->translate('bench.report.title'),
            '',
            $this->translator->translate('bench.report.config', ['config' => $report->options->signature()]),
            $this->translator->translate('bench.report.environment', [
                'php' => $environment->phpVersion,
                'imagick' => $environment->imageMagickVersion,
                'font' => $environment->font,
            ]),
            $this->translator->translate('bench.report.iterations', [
                'iterations' => $report->options->iterations,
                'warmup' => $report->options->warmupIterations,
            ]),
            '',
        ]);
    }

    private function scenarioTable(BenchmarkReport $report): string
    {
        $header = [
            $this->translator->translate('bench.column.scenario'),
            $this->translator->translate('bench.column.draw'),
            $this->translator->translate('bench.column.quantize'),
            $this->translator->translate('bench.column.encode'),
            $this->translator->translate('bench.column.total'),
            $this->translator->translate('bench.column.spread'),
            $this->translator->translate('bench.column.blob'),
        ];

        $rows = [$header];

        foreach ($report->results as $result) {
            $rows[] = [
                $this->translator->translate($result->scenario->labelKey()),
                $this->phase($result->draw, $result->total),
                $this->phase($result->quantize, $result->total),
                $this->phase($result->encode, $result->total),
                $this->milliseconds($result->total->median),
                $this->spread($result),
                $this->kilobytes($result->blobBytes->median),
            ];
        }

        $table = $this->layOut($rows, [false, true, true, true, true, true, true]);

        if ($report->hasUnstableResults()) {
            $table .= "\n" . $this->translator->translate('bench.report.unstableNote', [
                'ratio' => $this->translator->number(Measurement::UNSTABLE_SPREAD_RATIO, 2),
            ]) . "\n";
        }

        return $table;
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

    /** @param list<ComparisonRow> $rows */
    public function renderComparison(array $rows, string $baselinePath, float $thresholdPercent): string
    {
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

        return implode("\n", [
            '',
            $this->translator->translate('bench.compare.title', ['file' => basename($baselinePath)]),
            '',
            $this->layOut($table, [false, true, true, true]),
            $regressions === []
                ? $this->translator->translate('bench.compare.clean')
                : $this->translator->plural('bench.compare.regressions', count($regressions)),
            '',
        ]);
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
