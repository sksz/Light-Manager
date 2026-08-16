<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Process;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundState;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Application\Port\BackgroundPumpPort;
use LightManager\Infrastructure\Config\SettingsService;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Procesy potomne doglądane między klatkami.
 *
 * Cała klasa sprowadza się do jednego zdania: **żadne wywołanie stąd nie czeka
 * na potomka**. `start()` uruchamia i wraca, `pump()` zagląda do potoków i do
 * stanu procesów, po czym wraca niezależnie od tego, co zastało, a `poll()` czyta
 * już tylko gotową daną. Pętla główna nie ma prawa zauważyć, że coś obok trwa.
 *
 * **Od kroku 51 prac jest kilka naraz** (D90 nr 1), każda pod własnym uchwytem,
 * z granicą braną z ustawień. Zmiana dotknęła podziału pracy w tej klasie
 * bardziej niż jej rozmiaru: to, co dzieje się z **jednym** potomkiem, wyniosło
 * się w całości do `BackgroundJob`, a tutaj zostało to, czego jedna praca nie
 * potrafi — rozdawanie uchwytów, pilnowanie granicy i sprzątanie.
 *
 * Trzy rzeczy, których zaniedbanie kończy się nie błędem, tylko śmieciem
 * w systemie — i dlatego każda ma tu swoje miejsce:
 *
 * 1. **Potomek przeżywa proces macierzysty.** Wyjście z aplikacji — normalne
 *    i z sygnału — musi go ubić. Robią to dwie drogi naraz: jawne `shutdown()`
 *    na ścieżce wyjścia (`Bootstrap::shutdown()`) i funkcja zamknięcia procesu
 *    jako gwarancja ostatniej szansy. Pierwsza jest widoczna w kodzie, druga
 *    łapie to, czego pierwsza nie dosięga — błąd krytyczny i `exit()` z boku.
 *    Przy kilku pracach naraz obie drogi sprzątają **komplet**.
 * 2. **Potoki trzeba czytać, nawet gdy nikogo nie obchodzą.** Potomek, który
 *    zapełni potok, zatrzymuje się na zapisie i stoi tak do limitu czasu. Do
 *    kroku 51 karmił go jego właściciel przy każdym `poll()`; odkąd prac jest
 *    kilka, karmi je **pętla** przez `pump()` — bo właściciel niezaglądający
 *    (ekran modułu zniknął, moduł ma usterkę) zatrzymałby swojego potomka i nie
 *    zauważyłby tego nikt.
 * 3. **Zamknięty potomek trzeba pochować.** Bez `proc_close()` zostaje zombie,
 *    a bez `proc_terminate()` przed nim — działający potomek po zamknięciu
 *    aplikacji.
 *
 * Sygnałem przerwania jest `SIGKILL`, a nie `SIGTERM`, i to jest różnica wobec
 * grzeczności: przy wyjściu z aplikacji nie ma już komu poczekać, aż potomek
 * rozmyśli się nad obsługą sygnału.
 */
final class BackgroundProcessService extends AbstractSingleton implements BackgroundProcessPort, BackgroundPumpPort
{
    /**
     * Ile stanów prac **zakończonych** pamiętamy, zanim zaczniemy zapominać
     * najstarsze.
     *
     * Praca skończona nie trzyma już ani procesu, ani potoków — zostaje z niej
     * sam stan do odczytania, a zdejmuje go `stop()` wołany przez właściciela.
     * Ten zapas istnieje na wypadek właściciela, który `stop()` zapomni: bez
     * niego tablica rosłaby przez całe uruchomienie aplikacji. Wartość jest
     * hojna wobec każdego dzisiejszego odbiorcy — wszyscy odbierają wynik
     * w klatce, w której go zobaczyli.
     */
    private const RETAINED_FINISHED_JOBS = 32;

    /**
     * Prace tego uruchomienia — numer uchwytu → praca. Trwające i te, których
     * stanu nikt jeszcze nie zdjął.
     *
     * @var array<int, BackgroundJob>
     */
    private array $jobs = [];

    private int $lastId = 0;

    private bool $shutdownRegistered = false;

    public function start(string $command, int $timeoutSeconds): BackgroundHandle
    {
        $handle = new BackgroundHandle(++$this->lastId);
        $limit = self::jobLimitFromSettings();
        $job = new BackgroundJob(max(1, $timeoutSeconds), self::outputLimitFromSettings());

        // Granica pilnuje **prac trwających**, a nie wpisów w tablicy: praca
        // skończona, której stanu nikt jeszcze nie zdjął, nie zajmuje ani
        // procesu, ani potoków, więc odmowa z jej powodu byłaby odmową bez
        // powodu. Liczymy przed dopisaniem tej pracy, bo ona jeszcze nie trwa.
        $refused = $this->runningCount() >= $limit;

        $this->forgetOldestFinished();
        $this->jobs[$handle->id] = $job;

        if ($refused) {
            $job->refuse($limit);

            return $handle;
        }

        $job->start($command);
        $this->registerShutdownHandler();

        return $handle;
    }

    public function poll(BackgroundHandle $handle): BackgroundState
    {
        return ($this->jobs[$handle->id] ?? null)?->state() ?? BackgroundState::idle();
    }

    public function pump(): void
    {
        foreach ($this->jobs as $job) {
            $job->advance();
        }
    }

    public function stop(BackgroundHandle $handle): void
    {
        $job = $this->jobs[$handle->id] ?? null;

        if ($job === null) {
            return;
        }

        $job->release();
        unset($this->jobs[$handle->id]);
    }

    /**
     * Sprzątanie przy wyjściu z aplikacji — jedyna metoda spoza obu portów.
     *
     * Nie ma jej w porcie z rozmysłem: moduł zamawia pracę i ją przerywa, ale
     * o zamykaniu aplikacji nie wie i nie ma prawa wiedzieć. Woła ją
     * `Bootstrap::shutdown()` — tą samą ścieżką, którą terminal wraca do trybu
     * normalnego — oraz funkcja zamknięcia procesu zarejestrowana przy pierwszym
     * uruchomieniu.
     *
     * Od kroku 51 sprząta **wszystkie** prace i jest to zarazem odpowiedź na
     * pytanie, które plan tamtego kroku zadawał inaczej: sprzątania całości nie
     * dokłada się do portu jako `stopAll()`, bo droga wychodząca z aplikacji
     * istnieje tu od kroku 26 i nie jest dostępna modułom.
     */
    public function shutdown(): void
    {
        foreach ($this->jobs as $job) {
            $job->release();
        }

        $this->jobs = [];
    }

    private function runningCount(): int
    {
        $running = 0;

        foreach ($this->jobs as $job) {
            if ($job->isRunning()) {
                ++$running;
            }
        }

        return $running;
    }

    /**
     * Zapomina najstarsze prace zakończone, gdy uzbiera się ich ponad zapas.
     *
     * Zapominanie idzie **po kolejności zamówienia** (klucze rosną), a nie po
     * czasie zakończenia: praca zamówiona wcześniej ma za sobą więcej klatek,
     * w których jej właściciel mógł odebrać wynik.
     */
    private function forgetOldestFinished(): void
    {
        $finished = [];

        foreach ($this->jobs as $id => $job) {
            if (!$job->isRunning()) {
                $finished[] = $id;
            }
        }

        $excess = count($finished) - self::RETAINED_FINISHED_JOBS;

        for ($index = 0; $index < $excess; ++$index) {
            unset($this->jobs[$finished[$index]]);
        }
    }

    /**
     * Limit wyjścia obowiązujący **tę** pracę — brany raz, przy jej uruchomieniu.
     *
     * Raz, a nie co odczyt, i to jest cała reguła tego miejsca: praca, której
     * limit zmieniłby się w trakcie, zbierałaby wyjście wedle dwóch różnych
     * miar i nikt nie umiałby powiedzieć, ile jej w końcu wolno.
     *
     * **Stałej awaryjnej tu nie ma i nie potrzeba jej**: do kroku 49 limit był
     * wpisany w kod (64 KiB pod polecenia oddające jeden wiersz — `du -s`,
     * `file -b`), a od kroku 51 dolną granicę trzyma sam `Settings`, sprowadzając
     * wartość spoza listy do najmniejszego przystanku — czyli do tej samej
     * liczby, którą niosła tamta stała.
     */
    private static function outputLimitFromSettings(): int
    {
        return SettingsService::getInstance()->current()->backgroundOutputBytes();
    }

    /**
     * Granica liczby prac — czytana przy **każdym** uruchomieniu, inaczej niż
     * limit wyjścia.
     *
     * Różnica jest celowa: limit wyjścia opisuje pracę, więc zmiana w trakcie
     * czyniłaby jej wynik niepoliczalnym, a granica opisuje **zbiór prac**, więc
     * ustawienie zmienione na ekranie ma obowiązywać od następnego zamówienia,
     * a nie od następnego uruchomienia aplikacji.
     */
    private static function jobLimitFromSettings(): int
    {
        return SettingsService::getInstance()->current()->backgroundJobLimit();
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
