<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Process;

use LightManager\Application\Dto\BackgroundState;
use LightManager\Application\Dto\OutputShape;

/**
 * Jedna praca tłowa: proces potomny, jego dwa potoki i to, co z nich zebrano.
 *
 * Klasa powstała w kroku 51 i jest w całości **wyprowadzeniem pól, które do tego
 * kroku leżały w usłudze** — bo leżeć w niej mogły, dopóki praca była jedna.
 * Osiem pól opisujących „bieżącą pracę” przy kilku pracach naraz musiałoby stać
 * się ośmioma tablicami trzymanymi obok siebie i zgodnymi co do kluczy; jedna
 * niezgodność między nimi byłaby procesem, o którym nikt już nie pamięta.
 *
 * Usługą to nie jest i Singletonem być nie może (reguła 3 mówi o usługach):
 * to zapis wewnętrzny `BackgroundProcessService`, powoływany `new`-em i ginący
 * razem z pracą. Poza katalog `Infrastructure/Process` nie wychodzi — warstwa
 * aplikacji ogląda pracę wyłącznie przez `BackgroundState`.
 *
 * Trzy reguły z kroku 26 obowiązują tu bez zmian i każda ma swoje miejsce
 * w kodzie: **żadne wywołanie nie czeka na potomka**, **oba potoki są czytane**
 * (nieczytany zatrzymuje piszącego), **zamknięty potomek zostaje pochowany**
 * (`proc_close()`, inaczej zombie).
 */
final class BackgroundJob
{
    private const KILL_SIGNAL = 9;

    /** @var resource|null */
    private $process = null;

    /** @var array<int, resource> */
    private array $pipes = [];

    private float $deadline = 0.0;

    private string $output = '';

    private string $errorOutput = '';

    /** Ile bajtów wypadło z początku bufora, bo strumień się przesunął (krok 52). */
    private int $droppedBytes = 0;

    private BackgroundState $state;

    public function __construct(
        private readonly int $timeoutSeconds,
        private readonly int $outputLimit,
        /** Czym jest wypis tej pracy — od tego zależy, co bufor robi po przekroczeniu granicy. */
        private readonly OutputShape $shape = OutputShape::Result,
    ) {
        $this->state = BackgroundState::idle();
    }

    /**
     * Uruchamia polecenie i wraca **nie czekając**. Niepowodzenie zostaje
     * w stanie pracy, a nie leci wyjątkiem: uchwyt wrócił już do wołającego,
     * więc druga droga zgłaszania awarii nie miałaby kto odebrać.
     */
    public function start(string $command): void
    {
        if (!function_exists('proc_open')) {
            $this->state = BackgroundState::failed('process.unavailable');

            return;
        }

        $pipes = [];
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            $this->state = BackgroundState::failed('process.failed');

            return;
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
        $this->state = BackgroundState::running();
    }

    /**
     * Praca, która nie powstała, bo trwa już tyle, ile wolno.
     *
     * Odmowa jest **stanem pracy, a nie brakiem uchwytu** — wołający odbiera ją
     * tam, gdzie odbiera każdą inną awarię startu, czyli pierwszym `poll()`.
     */
    public function refuse(int $limit): void
    {
        $this->state = BackgroundState::failed('process.tooMany', ['limit' => $limit]);
    }

    public function state(): BackgroundState
    {
        return $this->state;
    }

    /** Czy praca zajmuje miejsce w granicy liczby prac. */
    public function isRunning(): bool
    {
        return $this->state->isRunning();
    }

    /**
     * Posuwa pracę o tyle, ile dało się bez czekania — jedno wywołanie na klatkę,
     * z `BackgroundPumpPort::pump()`.
     *
     * Kolejność wewnątrz nie jest dowolna: potoki czytamy **przed** sprawdzeniem
     * stanu procesu i **jeszcze raz po nim**. Pierwszy odczyt karmi potomka
     * (pełny potok go zatrzymuje), drugi zbiera to, co wypisał tuż przed
     * wyjściem — bez niego wynik polecenia kończącego się szybko po zapisie
     * przepadałby razem z zamkniętymi potokami.
     */
    public function advance(): void
    {
        $process = $this->process;

        if (!$this->state->isRunning() || !is_resource($process)) {
            return;
        }

        $this->drain();
        $status = proc_get_status($process);

        if ($status['running']) {
            if (microtime(true) < $this->deadline) {
                // Stan trwającej pracy niesie od kroku 52 to, co dotąd przyszło
                // (D91 nr 12). Powstaje **tutaj, a nie w `poll()`**, żeby
                // zaglądanie zostało czystym odczytem: dwa doglądania w tej samej
                // klatce mają oddać to samo.
                $this->state = BackgroundState::running(
                    $this->deliverableOutput(),
                    trim($this->errorOutput),
                    $this->droppedBytes,
                );

                return;
            }

            proc_terminate($process, self::KILL_SIGNAL);
            $this->release();
            $this->state = BackgroundState::failed('process.timedOut', ['seconds' => $this->timeoutSeconds]);

            return;
        }

        // Kod wyjścia bierzemy z tego właśnie odczytu stanu, bo `proc_close()`
        // po pochowaniu potomka oddaje już tylko −1.
        $this->drain();
        $output = $this->deliverableOutput();
        $errorOutput = $this->errorOutput;
        $this->release();
        $this->state = BackgroundState::done(
            $output,
            $status['exitcode'],
            trim($errorOutput),
            $this->droppedBytes,
        );
    }

    /**
     * Ubija potomka, zamyka potoki i chowa go. **Idempotentne** — wolno wołać
     * wielokrotnie i z dowolnej ścieżki wyjścia, bo dokładnie tak jest wołane:
     * przy przerwaniu, przy limicie czasu, przy zakończeniu i przy zamykaniu
     * aplikacji.
     *
     * Stanu nie rusza: przerwane sprzątanie i zakończona praca różnią się tym, co
     * po nich zostaje do obejrzenia, a nie tym, co po nich zostaje w systemie.
     */
    public function release(): void
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

    /**
     * Czyta to, co potomek zdążył wypisać, i **nie czeka na resztę**.
     *
     * Potoki są nieblokujące, więc `stream_get_contents()` oddaje tyle, ile stoi
     * w buforze, i wraca. **Oba strumienie idą do granicy z osobna**, każdy
     * z własnym limitem: `du` na katalogu domowym potrafi wypisać na strumieniu
     * błędów więcej, niż wynosi jego wynik, a wspólny licznik kazałby wynikowi
     * ustąpić narzekaniu. Nadmiar jest **czytany i wyrzucany** — przestać czytać
     * znaczyłoby zatrzymać potomka.
     */
    private function drain(): void
    {
        foreach ($this->pipes as $descriptor => $pipe) {
            if (!is_resource($pipe)) {
                continue;
            }

            $chunk = stream_get_contents($pipe);

            if ($chunk === false || $chunk === '') {
                continue;
            }

            if ($descriptor === 1) {
                $this->collect($chunk);

                continue;
            }

            // Strumień błędów zbiera się **zawsze jak wynik**, także przy pracy
            // strumieniowej: diagnostyka jest krótka i najciekawsza na początku
            // (pierwszy powód, dla którego coś poszło nie tak), a przesuwanie jej
            // bufora kazałoby zapominać właśnie ten wiersz.
            $this->errorOutput .= $this->fitting($chunk, $this->errorOutput);
        }
    }

    /**
     * Dokłada kawałek wypisu — **wynik odrzuca nadmiar, strumień zapomina
     * najstarsze** (krok 52).
     *
     * Dwie reguły zamiast jednej, bo dwa rodzaje wypisu mają przeciwne wymagania.
     * Wynik jest cenny od początku (suma `du` stoi w pierwszym wierszu), więc
     * granica ucina koniec i praca się na tym kończy. Strumień jest cenny **na
     * końcu** i nie kończy się nigdy, więc granica ucinająca koniec zamieniłaby
     * go po kilkunastu sekundach w ciszę — logi płynęłyby dalej, a aplikacja nie
     * dostawałaby ani jednego nowego wiersza.
     */
    private function collect(string $chunk): void
    {
        if ($this->shape === OutputShape::Result) {
            $this->output .= $this->fitting($chunk, $this->output);

            return;
        }

        $this->output .= $chunk;
        $excess = strlen($this->output) - $this->outputLimit;

        if ($excess <= 0) {
            return;
        }

        $this->output = substr($this->output, $excess);
        $this->droppedBytes += $excess;
    }

    /**
     * Wypis w postaci, w jakiej wychodzi do warstwy aplikacji.
     *
     * Wynik przycinamy z białych znaków — tak było od kroku 26 i tak zostaje.
     * Strumienia **nie przycinamy nigdy**: czytający liczy w nim pozycje
     * w bajtach, a przycięcie początku przesunęłoby mu je wszystkie naraz, i to
     * nie za każdym razem o tyle samo.
     */
    private function deliverableOutput(): string
    {
        return $this->shape === OutputShape::Stream ? $this->output : trim($this->output);
    }

    /** Tyle kawałka, ile mieści się w limicie; pusty napis, gdy nie mieści się nic. */
    private function fitting(string $chunk, string $collected): string
    {
        $room = $this->outputLimit - strlen($collected);

        return $room > 0 ? substr($chunk, 0, $room) : '';
    }
}
