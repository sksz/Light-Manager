<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

use LightManager\Application\Port\CommandHistoryPort;

/**
 * Ostatnio wpisane wiersze — dwadzieścia w pamięci i tyle samo w pliku.
 *
 * Do historii idzie **cały wiersz wraz z argumentami**, bo to argumentów nie
 * chce się wpisywać drugi raz; sama nazwa komendy jest krótka i tak czy owak
 * podpowiadana.
 *
 * **Zapis nie chodzi po każdym wywołaniu.** Historia trafia na dysk, gdy od
 * ostatniego zapisu uzbiera się pełny bufor, oraz przy zamknięciu aplikacji.
 * Gdyby zapis szedł co wywołanie, każde naciśnięcie `Enter` w oknie komend
 * kosztowałoby `rename()` — a gdyby szedł wyłącznie przy zamknięciu, awaryjne
 * ubicie procesu gubiłoby całą sesję.
 */
final class CommandHistory
{
    /** Ile wpisów mieści bufor i ile trafia do pliku. */
    public const CAPACITY = 20;

    /** @var list<string> od najstarszego */
    private array $entries;

    /** Ile wpisów doszło od ostatniego zapisu. */
    private int $unsaved = 0;

    public function __construct(
        private readonly CommandHistoryPort $port,
    ) {
        $this->entries = array_slice($port->load(), -self::CAPACITY);
    }

    /**
     * Wpis powtórzony **przesuwa się na koniec**, zamiast dokładać kopię: bufor
     * ma dwadzieścia miejsc, a dziesięciokrotne powtórzenie tego samego skoku
     * wyparłoby z niego całą resztę.
     */
    public function remember(string $line): void
    {
        $line = trim($line);

        if ($line === '') {
            return;
        }

        $this->entries = array_values(array_filter(
            $this->entries,
            static fn (string $entry): bool => $entry !== $line,
        ));

        $this->entries[] = $line;
        $this->entries = array_slice($this->entries, -self::CAPACITY);
        ++$this->unsaved;

        if ($this->unsaved >= self::CAPACITY) {
            $this->flush();
        }
    }

    /** @return list<string> od najnowszego — w tej kolejności widać je w oknie */
    public function entries(): array
    {
        return array_reverse($this->entries);
    }

    /** Zapisuje, o ile jest co zapisywać. Wołane przy zamknięciu aplikacji. */
    public function flush(): void
    {
        if ($this->unsaved === 0) {
            return;
        }

        $this->port->save($this->entries);
        $this->unsaved = 0;
    }
}
