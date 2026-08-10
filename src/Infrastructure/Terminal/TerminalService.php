<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Port\TerminalPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Przełącza terminal w tryb surowy i udostępnia nieblokujący odczyt klawiszy.
 *
 * Konstruktor ma efekt uboczny (wejście w tryb raw), dlatego usługa jest
 * pierwsza w kolejności bootstrapu. Przywrócenie ustawień terminala jest
 * zabezpieczone trzytorowo: obsługą sygnałów, funkcją zamknięcia procesu
 * i jawnym wywołaniem `restore()`.
 */
final class TerminalService extends AbstractSingleton implements TerminalPort
{
    private const RAW_MODE_SETTINGS = '-icanon -echo -ixon min 1 time 0';

    private const READ_CHUNK_BYTES = 1024;

    /** Ile razy z rzędu wolno przyjąć zapis zerowej długości, zanim uznamy terminal za martwy. */
    private const WRITE_STALL_LIMIT = 1000;

    private const WRITE_STALL_PAUSE_MICROSECONDS = 1000;

    /** Okno na dosłanie reszty sekwencji escape przez terminal. */
    private const SEQUENCE_TAIL_TIMEOUT_MICROSECONDS = 20000;

    /** Sygnały, po których terminal musi wrócić do stanu sprzed uruchomienia. */
    private const HANDLED_SIGNALS = [SIGINT, SIGTERM, SIGHUP, SIGQUIT];

    private const ENTER_ALTERNATE_SCREEN = "\e[?1049h";

    private const LEAVE_ALTERNATE_SCREEN = "\e[?1049l";

    private const HIDE_CURSOR = "\e[?25l";

    private const SHOW_CURSOR = "\e[?25h";

    private readonly KeySequenceParser $parser;

    private readonly string $originalSettings;

    private bool $rawModeActive = false;

    private bool $alternateScreenActive = false;

    private bool $shutdownRequested = false;

    private string $buffer = '';

    protected function __construct()
    {
        parent::__construct();

        if (!function_exists('pcntl_async_signals')) {
            throw TerminalException::forMissingPcntl();
        }

        if (!stream_isatty(STDIN)) {
            throw TerminalException::forNonInteractiveStdin();
        }

        $this->parser = new KeySequenceParser();
        $this->originalSettings = $this->runStty('-g');

        $this->enterRawMode();
        $this->registerSignalHandlers();
        $this->registerShutdownHandler();
    }

    public function readKey(): ?KeyPress
    {
        $this->buffer .= $this->readAvailableBytes(0);

        $parsed = $this->parser->parse($this->buffer);

        if ($parsed === null) {
            $this->buffer .= $this->readAvailableBytes(self::SEQUENCE_TAIL_TIMEOUT_MICROSECONDS);
            $parsed = $this->parser->parseAfterTimeout($this->buffer);
        }

        if ($parsed === null) {
            return null;
        }

        $this->buffer = substr($this->buffer, $parsed->consumedBytes);

        return $parsed->keyPress;
    }

    public function shutdownRequested(): bool
    {
        return $this->shutdownRequested;
    }

    /**
     * Surowy zapis na terminal — poza kontraktem `TerminalPort`, do użytku
     * przez inne usługi `Infrastructure` (zapytania do terminala, wypychanie
     * klatki).
     *
     * @return int liczba wywołań `fwrite()`, które złożyły się na ten zapis;
     *             aplikacja tę wartość ignoruje, ale narzędzie pomiarowe
     *             z kroku 16 raportuje ją jako „liczbę iteracji dopisywania”
     *             (krok 09 odnotował `fwrite()` przyjmujące 8192 B z ~9,5 kB).
     *             To zwykły wynik metody, nie instrumentacja: pętla poniżej
     *             liczyłaby te iteracje tak czy inaczej.
     */
    public function write(string $data): int
    {
        $remaining = $data;
        $stalledAttempts = 0;
        $chunks = 0;

        // `fwrite()` potrafi przyjąć mniej, niż mu dano — przy klatce Sixel
        // większej niż bufor potoku (8 kB) reszta bajtów po prostu przepada,
        // a terminal dostaje uciętą, uszkodzoną klatkę. Zapis musi być
        // dopisywany do skutku.
        while ($remaining !== '') {
            $written = fwrite(STDOUT, $remaining);
            ++$chunks;

            if ($written === false) {
                // Terminal zniknął (np. zamknięte okno) — nie ma komu zgłosić
                // błędu, a rzucanie wyjątku popsułoby ścieżkę przywracania.
                return $chunks;
            }

            if ($written === 0) {
                if (++$stalledAttempts > self::WRITE_STALL_LIMIT) {
                    return $chunks;
                }

                // Odbiorca nie nadąża. Krótka przerwa zamiast kręcenia się w
                // miejscu — pętla nie może tu utknąć, bo od kroku 09 sygnały
                // tylko ustawiają znacznik i nie ubijają procesu.
                usleep(self::WRITE_STALL_PAUSE_MICROSECONDS);

                continue;
            }

            $stalledAttempts = 0;
            $remaining = substr($remaining, $written);
        }

        fflush(STDOUT);

        return $chunks;
    }

    /**
     * Surowy odczyt z pominięciem parsera klawiszy — potrzebny tam, gdzie
     * odpowiedzią terminala jest sekwencja escape, którą `readKey()` rozbiłoby
     * na osobne zdarzenia.
     *
     * Bajty zebrane wcześniej przez `readKey()` są zwracane razem z nowymi,
     * żeby żadne wejście nie zostało uwięzione w buforze.
     */
    public function readRawBytes(int $timeoutMicroseconds): string
    {
        $pending = $this->buffer;
        $this->buffer = '';

        return $pending . $this->readAvailableBytes($timeoutMicroseconds);
    }

    /**
     * Przełącza terminal na osobny ekran (jak vim czy htop), żeby klatki nie
     * zamazywały historii powłoki. Wołane przez renderer, nie przez konstruktor
     * — dzięki temu narzędzia korzystające z samego wejścia (np.
     * `bin/terminal-probe`) zostawiają swoje wypisane wiersze na ekranie.
     */
    public function enterAlternateScreen(): void
    {
        if ($this->alternateScreenActive) {
            return;
        }

        $this->alternateScreenActive = true;
        $this->write(self::ENTER_ALTERNATE_SCREEN . self::HIDE_CURSOR);
    }

    /** @return array{columns: int, rows: int}|null `null`, gdy `stty` zwróci coś nieoczekiwanego */
    public function sizeInCells(): ?array
    {
        $matches = [];

        if (preg_match('/^(\d+)\s+(\d+)$/', $this->runStty('size'), $matches) !== 1) {
            return null;
        }

        $rows = (int) $matches[1];
        $columns = (int) $matches[2];

        if ($rows <= 0 || $columns <= 0) {
            return null;
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    /**
     * Oddaje bajty, które okazały się nie należeć do trwającego odczytu.
     *
     * Przy starcie leci więcej niż jedno zapytanie do terminala i odpowiedzi
     * potrafią przyjść w innej kolejności, niż je wysłano — bez zwracania
     * nadmiaru czytnik jednego zapytania połknąłby odpowiedź drugiego.
     */
    public function pushBackBytes(string $bytes): void
    {
        if ($bytes === '') {
            return;
        }

        $this->buffer = $bytes . $this->buffer;
    }

    /** Idempotentne — bezpieczne do wywołania wielokrotnie i z dowolnej ścieżki wyjścia. */
    public function restore(): void
    {
        $this->leaveAlternateScreen();

        if (!$this->rawModeActive) {
            return;
        }

        $this->rawModeActive = false;

        stream_set_blocking(STDIN, true);
        $this->runStty(escapeshellarg($this->originalSettings));
    }

    private function leaveAlternateScreen(): void
    {
        if (!$this->alternateScreenActive) {
            return;
        }

        $this->alternateScreenActive = false;
        $this->write(self::SHOW_CURSOR . self::LEAVE_ALTERNATE_SCREEN);
    }

    private function enterRawMode(): void
    {
        $this->runStty(self::RAW_MODE_SETTINGS);
        stream_set_blocking(STDIN, false);

        $this->rawModeActive = true;
    }

    private function registerSignalHandlers(): void
    {
        pcntl_async_signals(true);

        foreach (self::HANDLED_SIGNALS as $signal) {
            // Handler celowo nie kończy procesu: pętla gry ma dostać szansę
            // wyjść przez `break` i posprzątać po sobie jedną ścieżką, wspólną
            // dla klawisza wyjścia i sygnału. Gwarancję przywrócenia terminala
            // trzyma funkcja zamknięcia procesu, nie ten handler.
            pcntl_signal($signal, function (): void {
                $this->shutdownRequested = true;
            });
        }
    }

    private function registerShutdownHandler(): void
    {
        register_shutdown_function(function (): void {
            $this->restore();
        });
    }

    private function readAvailableBytes(int $timeoutMicroseconds): string
    {
        $read = [STDIN];
        $write = null;
        $except = null;

        // Sygnał doręczony w trakcie oczekiwania przerywa wywołanie systemowe (EINTR),
        // a PHP raportuje to ostrzeżeniem wprost na terminal — co zniszczyłoby
        // rysowaną klatkę. Dla pętli gry to nie błąd: to po prostu brak wejścia.
        $ready = @stream_select($read, $write, $except, 0, $timeoutMicroseconds);

        if ($ready === false || $ready === 0) {
            return '';
        }

        $bytes = fread(STDIN, self::READ_CHUNK_BYTES);

        return $bytes === false ? '' : $bytes;
    }

    private function runStty(string $arguments): string
    {
        if (!function_exists('exec')) {
            throw TerminalException::forDisabledExec();
        }

        $output = [];
        $exitCode = 0;

        exec('stty ' . $arguments . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw TerminalException::forSttyFailure($arguments, $exitCode, implode(' ', $output));
        }

        return trim(implode('', $output));
    }
}
