<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Process;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundState;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Proces potomny doglądany między klatkami.
 *
 * Cała klasa sprowadza się do jednego zdania: **żadne wywołanie stąd nie czeka
 * na potomka**. `start()` uruchamia i wraca, `poll()` zagląda do potoków i do
 * stanu procesu, po czym wraca niezależnie od tego, co zastało. Pętla główna nie
 * ma prawa zauważyć, że coś obok trwa.
 *
 * Trzy rzeczy, których zaniedbanie kończy się nie błędem, tylko śmieciem
 * w systemie — i dlatego każda ma tu swoje miejsce:
 *
 * 1. **Potomek przeżywa proces macierzysty.** Wyjście z aplikacji — normalne
 *    i z sygnału — musi go ubić. Robią to dwie drogi naraz: jawne `shutdown()`
 *    na ścieżce wyjścia (`Bootstrap::shutdown()`) i funkcja zamknięcia procesu
 *    jako gwarancja ostatniej szansy. Pierwsza jest widoczna w kodzie, druga
 *    łapie to, czego pierwsza nie dosięga — błąd krytyczny i `exit()` z boku.
 * 2. **Potoki trzeba czytać, nawet gdy nikogo nie obchodzą.** Potomek, który
 *    zapełni potok, zatrzymuje się na zapisie i stoi tak do limitu czasu; `du`
 *    na katalogu domowym wypisuje na strumieniu błędów setki wierszy „brak
 *    dostępu”. Strumień błędów jest więc czytany i **wyrzucany**, a nie
 *    ignorowany — to nie to samo.
 * 3. **Zamknięty potomek trzeba pochować.** Bez `proc_close()` zostaje zombie,
 *    a bez `proc_terminate()` przed nim — działający potomek po zamknięciu
 *    aplikacji.
 *
 * Sygnałem przerwania jest `SIGKILL`, a nie `SIGTERM`, i to jest różnica wobec
 * grzeczności: przy wyjściu z aplikacji nie ma już komu poczekać, aż potomek
 * rozmyśli się nad obsługą sygnału.
 */
final class BackgroundProcessService extends AbstractSingleton implements BackgroundProcessPort
{
    /**
     * Ile najwyżej bajtów wyjścia pamiętamy.
     *
     * Polecenie zamówione przez moduł ma podać wynik, a nie zapełnić pamięć:
     * `du -s` wypisuje jeden wiersz, `file -b` jeden wiersz. Sześćdziesiąt cztery
     * kibibajty to zapas o trzy rzędy wielkości i zarazem twarda granica dla
     * polecenia, które zaczęłoby sypać bez końca. Nadmiar jest **czytany
     * i wyrzucany**, bo przestać czytać znaczyłoby zatrzymać potomka.
     */
    private const MAX_OUTPUT_BYTES = 64 * 1024;

    private const KILL_SIGNAL = 9;

    /** Uchwyt bieżącej pracy; `null` — nic nie zamówiono albo już posprzątano. */
    private ?BackgroundHandle $current = null;

    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private float $deadline = 0.0;

    private int $timeoutSeconds = 0;

    private string $output = '';

    private int $lastId = 0;

    private bool $shutdownRegistered = false;

    private BackgroundState $state;

    protected function __construct()
    {
        $this->state = BackgroundState::idle();
    }

    public function start(string $command, int $timeoutSeconds): BackgroundHandle
    {
        $this->release();

        $handle = new BackgroundHandle(++$this->lastId);
        $this->current = $handle;
        $this->timeoutSeconds = max(1, $timeoutSeconds);

        if (!function_exists('proc_open')) {
            $this->state = BackgroundState::failed('process.unavailable');

            return $handle;
        }

        $pipes = [];
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            $this->state = BackgroundState::failed('process.failed');

            return $handle;
        }

        // Bez tego pierwszy odczyt z potoku, do którego potomek jeszcze nic nie
        // napisał, zatrzymałby klatkę — czyli dokładnie to, czemu ta klasa ma
        // zapobiegać.
        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $this->process = $process;
        $this->pipes = $pipes;
        $this->deadline = microtime(true) + $this->timeoutSeconds;
        $this->output = '';
        $this->state = BackgroundState::running();

        $this->registerShutdownHandler();

        return $handle;
    }

    public function poll(BackgroundHandle $handle): BackgroundState
    {
        if (!$this->isCurrent($handle)) {
            return BackgroundState::idle();
        }

        $process = $this->process;

        if (!$this->state->isRunning() || !is_resource($process)) {
            return $this->state;
        }

        $this->drain();
        $status = proc_get_status($process);

        if ($status['running']) {
            if (microtime(true) < $this->deadline) {
                return $this->state;
            }

            proc_terminate($process, self::KILL_SIGNAL);
            $this->release();

            return $this->state = BackgroundState::failed(
                'process.timedOut',
                ['seconds' => $this->timeoutSeconds],
            );
        }

        // Potomek skończył, ale w potokach może jeszcze coś stać: zamknięcie ich
        // przed ostatnim odczytem zgubiłoby wynik polecenia, które wypisało go
        // tuż przed wyjściem. Kod wyjścia bierzemy z tego właśnie odczytu stanu,
        // bo `proc_close()` po pochowaniu potomka oddaje już tylko −1.
        $this->drain();
        $output = $this->output;
        $exitCode = $status['exitcode'];
        $this->release();

        return $this->state = BackgroundState::done(trim($output), $exitCode);
    }

    public function stop(BackgroundHandle $handle): void
    {
        if (!$this->isCurrent($handle)) {
            return;
        }

        $this->release();
        $this->state = BackgroundState::idle();
        $this->current = null;
    }

    /**
     * Sprzątanie przy wyjściu z aplikacji — jedyna metoda spoza portu.
     *
     * Nie ma jej w porcie z rozmysłem: moduł zamawia pracę i ją przerywa, ale
     * o zamykaniu aplikacji nie wie i nie ma prawa wiedzieć. Woła ją
     * `Bootstrap::shutdown()` — tą samą ścieżką, którą terminal wraca do trybu
     * normalnego — oraz funkcja zamknięcia procesu zarejestrowana przy pierwszym
     * uruchomieniu.
     */
    public function shutdown(): void
    {
        $this->release();
        $this->state = BackgroundState::idle();
        $this->current = null;
    }

    private function isCurrent(BackgroundHandle $handle): bool
    {
        return $this->current !== null && $this->current->equals($handle);
    }

    /**
     * Czyta to, co potomek zdążył wypisać, i **nie czeka na resztę**.
     *
     * Potoki są nieblokujące, więc `stream_get_contents()` oddaje tyle, ile
     * stoi w buforze, i wraca. Standardowe wyjście zbieramy do granicy rozmiaru,
     * strumień błędów wyrzucamy — ale czytamy oba, bo nieczytany potok zatrzymuje
     * potomka.
     */
    private function drain(): void
    {
        foreach ($this->pipes as $descriptor => $pipe) {
            if (!is_resource($pipe)) {
                continue;
            }

            $chunk = stream_get_contents($pipe);

            if ($chunk === false || $chunk === '' || $descriptor !== 1) {
                continue;
            }

            $room = self::MAX_OUTPUT_BYTES - strlen($this->output);

            if ($room > 0) {
                $this->output .= substr($chunk, 0, $room);
            }
        }
    }

    /**
     * Ubija potomka, zamyka potoki i chowa go. **Idempotentne** — wolno wołać
     * wielokrotnie i z dowolnej ścieżki wyjścia, bo dokładnie tak jest wołane:
     * przy przerwaniu, przy limicie czasu, przy zakończeniu i przy zamykaniu
     * aplikacji.
     *
     * Stanu ani uchwytu nie rusza: przerwane sprzątanie i zakończona praca różnią
     * się tym, co po nich zostaje do obejrzenia, a nie tym, co po nich zostaje
     * w systemie.
     */
    private function release(): void
    {
        $process = $this->process;

        if (is_resource($process)) {
            if (proc_get_status($process)['running']) {
                proc_terminate($process, self::KILL_SIGNAL);
            }

            foreach ($this->pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }

            proc_close($process);
        }

        $this->process = null;
        $this->pipes = [];
        $this->deadline = 0.0;
    }

    private function registerShutdownHandler(): void
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $this->shutdownRegistered = true;

        // Rejestracja jest **leniwa**: aplikacja, która nie zamówiła ani jednej
        // pracy, nie ma czego sprzątać, a funkcja zamknięcia procesu dopisana
        // na wszelki wypadek byłaby kosztem ponoszonym przez wszystkich za
        // przypadek nielicznych.
        register_shutdown_function(function (): void {
            $this->shutdown();
        });
    }
}
