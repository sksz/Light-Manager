<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

use LightManager\Application\Dto\InputEvent;
use LightManager\Application\Port\InputPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Przełącza terminal w tryb surowy i udostępnia nieblokujący odczyt wejścia.
 *
 * Konstruktor ma efekt uboczny (wejście w tryb raw), dlatego usługa jest
 * pierwsza w kolejności bootstrapu. Przywrócenie ustawień terminala jest
 * zabezpieczone trzytorowo: obsługą sygnałów, funkcją zamknięcia procesu
 * i jawnym wywołaniem `restore()`.
 *
 * Od kroku 55 **czwartą rzeczą pod tą samą gwarancją jest raportowanie myszy**
 * — i jest to jedyny powód, dla którego wolno je w ogóle włączyć. Raportowanie
 * niezdjęte przy wyjściu zostawia użytkownikowi terminal sypiący sekwencjami
 * przy każdym ruchu myszy: awaria widoczna długo po zamknięciu aplikacji,
 * a niedająca się z nią powiązać.
 */
final class TerminalService extends AbstractSingleton implements InputPort
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

    /**
     * Raportowanie wskaźnika: naciśnięcia i zwolnienia (`1000`), ruch
     * **wyłącznie przy wciśniętym przycisku** (`1002`) oraz tryb SGR (`1006`).
     *
     * Dwa wybory warte zapamiętania. **`1002`, a nie `1003`**: raportowanie
     * każdego ruchu wysyłałoby zdarzenie na każdą przekroczoną komórkę, czyli
     * zalewałoby pętlę wejściem, którego nikt nie zamawiał — a jedyne, co by
     * z tego wynikło, to podpowiedzi pod kursorem, których krok 55 nie ma
     * w zakresie. **`1006` jest obowiązkowy**, nie dodatkowy: kodowanie domyślne
     * zapisuje współrzędną jako bajt z przesunięciem 32, więc powyżej 223.
     * kolumny przestaje działać.
     */
    private const ENABLE_MOUSE_REPORTING = "\e[?1000h\e[?1002h\e[?1006h";

    /** Zdejmowanie w odwrotnej kolejności — jak przy każdym trybie prywatnym. */
    private const DISABLE_MOUSE_REPORTING = "\e[?1006l\e[?1002l\e[?1000l";

    private readonly KeySequenceParser $parser;

    private readonly string $originalSettings;

    private bool $rawModeActive = false;

    private bool $alternateScreenActive = false;

    private bool $mouseReportingActive = false;

    private bool $shutdownRequested = false;

    private bool $windowResized = false;

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

    public function readEvent(): ?InputEvent
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

        return $parsed->event;
    }

    /**
     * Włącza albo zdejmuje raportowanie wskaźnika — **w locie**, a nie dopiero
     * przy następnym uruchomieniu (krok 55, punkt 6 planu).
     *
     * Sekwencja wyłączająca jest dokładnie tą, którą wysyła `restore()`, i to
     * nie jest oszczędność: gdyby wyłączenie w ustawieniach szło inną drogą niż
     * wyjście z aplikacji, jedna z nich mogłaby przestać działać niezauważenie.
     */
    public function useMouseReporting(bool $enabled): void
    {
        if ($enabled === $this->mouseReportingActive) {
            return;
        }

        $this->mouseReportingActive = $enabled;
        $this->write($enabled ? self::ENABLE_MOUSE_REPORTING : self::DISABLE_MOUSE_REPORTING);
    }

    public function shutdownRequested(): bool
    {
        return $this->shutdownRequested;
    }

    /**
     * Oddaje i gasi znacznik zmiany rozmiaru okna (`SIGWINCH`).
     *
     * Poza kontraktem `InputPort`, jak `sizeInCells()`: rdzeń o rozmiarze
     * rozmawia wyłącznie przez `ViewportPort`, a świeżość odpowiedzi jest
     * sprawą usług terminala (krok 33). Zgaszenie **przed** pomiarem jest
     * celowe — sygnał doręczony w trakcie pomiaru ustawi znacznik ponownie
     * i następna klatka zmierzy jeszcze raz, zamiast zgubić zmianę.
     */
    public function consumeWindowResize(): bool
    {
        if (!$this->windowResized) {
            return false;
        }

        $this->windowResized = false;

        return true;
    }

    /**
     * Surowy zapis na terminal — poza kontraktem `InputPort`, do użytku
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
        // Raportowanie myszy schodzi **pierwsze**, przed ekranem zapasowym:
        // sekwencja wyłączająca musi trafić na ten sam ekran, na którym
        // włączono raportowanie, a po `1049l` terminal jest już z powrotem
        // w historii powłoki.
        $this->useMouseReporting(false);
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

    /**
     * Tryb surowy wraz z raportowaniem wskaźnika.
     *
     * Raportowanie wchodzi **tutaj**, a nie przy pierwszym pytaniu o klatkę, bo
     * to tryb surowy jest tym, co czyni wejście wejściem aplikacji, a nie
     * powłoki — i bo dzięki temu dostaje je także `bin/terminal-probe`, czyli
     * jedyne narzędzie, którym da się zobaczyć, co terminal naprawdę przysyła
     * (reguła 18). Ustawienie użytkownika zdejmuje je zaraz potem, w `Bootstrapie`.
     *
     * Uwaga na tryb surowy, której krok 55 nie zmienia, a która przesądziła
     * o klawiszach schowka w kroku 57: `isig` i `iexten` **zostają włączone**
     * (sprawdzone w prawdziwym pty: `intr = ^C`, `lnext = ^V`). Klawiatura
     * generuje przez to SIGINT, a `^V` połyka następny bajt.
     */
    private function enterRawMode(): void
    {
        $this->runStty(self::RAW_MODE_SETTINGS);
        stream_set_blocking(STDIN, false);

        $this->rawModeActive = true;
        $this->useMouseReporting(true);
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

        // Zmiana rozmiaru okna tym samym wzorcem: uchwyt ustawia znacznik
        // i nic więcej. Pomiar tutaj dotykałby STDIN w nieprzewidywalnym
        // momencie klatki; znacznik zdejmuje usługa rozmiaru, a serie
        // sygnałów z przeciągania rogu okna składają się przez to same do
        // jednego pomiaru na klatkę.
        pcntl_signal(SIGWINCH, function (): void {
            $this->windowResized = true;
        });
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
