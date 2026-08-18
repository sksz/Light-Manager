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
    private const FILE = 'history';

    private const TEMPORARY_PREFIX = '.history-';

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
        // Wspólna droga zapisu (`StateFile`, krok 59); wynik ginie po cichu,
        // bo taki jest kontrakt historii — aplikacja działa bez niej tak samo.
        StateFile::write(
            $this->directory(),
            self::FILE,
            self::TEMPORARY_PREFIX,
            implode("\n", array_slice($entries, -CommandHistory::CAPACITY)),
        );
    }

    public function location(): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR . self::FILE;
    }

    /** Katalog stanu — od kroku 59 zna go jedno miejsce (`StateFile`). */
    private function directory(): string
    {
        return StateFile::directory();
    }
}
