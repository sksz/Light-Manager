<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Infrastructure;

use LightManager\Application\Command\CommandLineParser;
use LightManager\Infrastructure\I18n\TranslatorService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\FileInfo\Application\Port\FileInspectorPort;

/**
 * Opisuje plik zewnętrznym poleceniem `file`.
 *
 * Flaga `-b` obcina z wyjścia ścieżkę, bo nazwa wpisu trafia do nagłówka.
 * `--` zamyka listę opcji, żeby nazwa zaczynająca się od myślnika nie została
 * wzięta za flagę.
 *
 * Od kroku 20 usługa mieszka w warstwie `Infrastructure` **modułu** i słucha
 * jego ustawień: limitu czasu i dodatkowych argumentów. Singletonem pozostaje na
 * dotychczasowych zasadach — moduł powtarza wewnątrz podział rdzenia, więc jego
 * usługi podlegają tym samym regułom, co usługi rdzenia.
 *
 * **Polecenie idzie przez `proc_open`, a nie przez `exec`**, i to jest jedyny
 * powód, dla którego kod się tu zmienił: `exec()` czeka, aż proces skończy, a
 * limit czasu bez możliwości przerwania procesu jest limitem tylko z nazwy.
 * Zawieszone `file` na uszkodzonym pliku sieciowym potrafi zatrzymać całą pętlę.
 */
final class FileInspectorService extends AbstractSingleton implements FileInspectorPort
{
    private const COMMAND = 'file -b';

    /** Co ile sekund zaglądamy, czy proces już skończył. */
    private const POLL_SECONDS = 0.01;

    private ?CommandLineParser $parser = null;

    public function describe(string $path, int $timeoutSeconds, string $arguments): string
    {
        $translator = TranslatorService::getInstance();

        if (!function_exists('proc_open')) {
            return $translator->translate('module.file-info.execDisabled');
        }

        [$output, $exitCode, $timedOut] = $this->run($this->command($path, $arguments), $timeoutSeconds);

        if ($timedOut) {
            return $translator->translate('module.file-info.timedOut', ['seconds' => $timeoutSeconds]);
        }

        $description = trim($output);

        if ($exitCode !== 0) {
            return $description === ''
                ? $translator->translate('module.file-info.failed')
                : $translator->translate('module.file-info.failedWith', ['detail' => $description]);
        }

        // Sam opis pochodzi od polecenia `file` i mówi językiem systemu — to
        // jego wyjście, nie nasz napis, więc nie przechodzi przez katalog.
        return $description === '' ? $translator->translate('module.file-info.empty') : $description;
    }

    /**
     * Polecenie wraz z argumentami użytkownika.
     *
     * Argumenty rozbiera **ten sam parser, co wiersz komend** — jedna reguła
     * cytowania w całej aplikacji zamiast dwóch, które trzeba by tłumaczyć
     * osobno. Każde słowo idzie potem przez `escapeshellarg()`, więc znak
     * specjalny przepuszczony przez wzorzec zostaje argumentem, a nie drugim
     * poleceniem.
     */
    private function command(string $path, string $arguments): string
    {
        $words = [];

        foreach ($this->parser()->words($arguments) as $word) {
            $words[] = escapeshellarg($word);
        }

        $extra = $words === [] ? '' : ' ' . implode(' ', $words);

        return self::COMMAND . $extra . ' -- ' . escapeshellarg($path) . ' 2>&1';
    }

    /**
     * Uruchamia polecenie i czeka na nie **nie dłużej niż wolno**.
     *
     * @return array{string, int, bool} wyjście, kod wyjścia i to, czy minął czas
     */
    private function run(string $command, int $timeoutSeconds): array
    {
        $pipes = [];
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return ['', 1, false];
        }

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $deadline = microtime(true) + max(1, $timeoutSeconds);
        $output = '';
        $timedOut = false;

        while (true) {
            $output .= self::drain($pipes);

            if (!proc_get_status($process)['running']) {
                break;
            }

            if (microtime(true) >= $deadline) {
                $timedOut = true;
                proc_terminate($process, 9);

                break;
            }

            usleep((int) (self::POLL_SECONDS * 1_000_000));
        }

        $output .= self::drain($pipes);

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        return [$output, proc_close($process), $timedOut];
    }

    /** @param array<int, resource> $pipes */
    private static function drain(array $pipes): string
    {
        $output = '';

        foreach ($pipes as $pipe) {
            $chunk = stream_get_contents($pipe);

            if (is_string($chunk)) {
                $output .= $chunk;
            }
        }

        return $output;
    }

    private function parser(): CommandLineParser
    {
        return $this->parser ??= new CommandLineParser(TranslatorService::getInstance());
    }
}
