<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Config;

use LightManager\Application\Command\CommandHistory;
use LightManager\Application\Port\CommandHistoryPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Historia komend w pliku `~/.light-manager/history`.
 *
 * **Osobny plik, nie klucz w `settings.json`**: to nie jest ustawienie, tylko
 * ślad pracy — ma inny cykl życia, inną częstotliwość zapisu i nie ma czego
 * szukać w konfiguracji, którą użytkownik bywa skłonny edytować ręcznie.
 *
 * Zapis idzie tą samą drogą co konfiguracja — plik tymczasowy i `rename()`
 * w tym samym katalogu — więc przerwany zapis zostawia poprzednią, poprawną
 * wersję zamiast obciętej. Plik nadpisujemy w całości, bo trzyma dokładnie tyle
 * wpisów, ile bufor: dopisywanie wymagałoby przycinania go przy odczycie i tak.
 *
 * Żadna ścieżka nie kończy się wyjątkiem (zasada portu): historia, której nie
 * dało się wczytać, jest pusta, a historia, której nie dało się zapisać, ginie
 * po cichu. Aplikacja działa bez niej tak samo.
 */
final class CommandHistoryService extends AbstractSingleton implements CommandHistoryPort
{
    private const DIRECTORY = '.light-manager';

    private const FILE = 'history';

    private const TEMPORARY_PREFIX = '.history-';

    /** Właściciel czyta i pisze, reszta świata nic — wpisy bywają ścieżkami. */
    private const FILE_MODE = 0o600;

    private const DIRECTORY_MODE = 0o700;

    public function load(): array
    {
        $path = $this->location();

        if (!is_file($path)) {
            return [];
        }

        $raw = @file_get_contents($path);

        if ($raw === false) {
            return [];
        }

        $lines = preg_split('/\R/u', $raw);

        if ($lines === false) {
            return [];
        }

        $entries = array_values(array_filter(
            array_map(trim(...), $lines),
            static fn (string $line): bool => $line !== '',
        ));

        return array_slice($entries, -CommandHistory::CAPACITY);
    }

    public function save(array $entries): void
    {
        $directory = $this->directory();

        if (!is_dir($directory) && !@mkdir($directory, self::DIRECTORY_MODE, true) && !is_dir($directory)) {
            return;
        }

        $temporary = $directory . DIRECTORY_SEPARATOR . self::TEMPORARY_PREFIX . getmypid() . '.tmp';
        $content = implode("\n", array_slice($entries, -CommandHistory::CAPACITY));

        if (@file_put_contents($temporary, $content . "\n") === false) {
            return;
        }

        @chmod($temporary, self::FILE_MODE);

        if (!@rename($temporary, $this->location())) {
            @unlink($temporary);
        }
    }

    public function location(): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR . self::FILE;
    }

    /** Katalog domowy z `HOME`, a w jego braku — katalog roboczy (jak w konfiguracji). */
    private function directory(): string
    {
        $home = getenv('HOME');

        if (!is_string($home) || $home === '') {
            $working = getcwd();
            $home = $working === false ? '.' : $working;
        }

        return rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DIRECTORY;
    }
}
