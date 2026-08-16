<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundState;
use LightManager\Application\Port\BackgroundProcessPort;

/**
 * Praca tłowa bez procesu: kończy się po zadanej liczbie doglądań.
 *
 * Powód jest ten sam, co przy `StubChecksums`, ale stawka wyższa: test, który
 * uruchamiałby prawdziwe `du`, zależałby od zawartości dysku maszyny, na której
 * akurat biegnie, a przy okazji zostawiałby po sobie procesy, gdyby przestał
 * przechodzić w połowie. Tutaj liczba doglądań jest wprost powiedziana, więc da
 * się sprawdzić to, co naprawdę jest do sprawdzenia: **że praca jest doglądana
 * co klatkę, że da się ją przerwać i że nikt nie czeka na jej koniec**.
 *
 * **Od kroku 51 prac jest kilka naraz**, każda ze swoim licznikiem doglądań —
 * bo tyle właśnie prowadzi prawdziwy port. Atrapa, która nadal prowadziłaby
 * jedną, kazałaby modułowi Dockera przechodzić testy w warunkach, w których
 * `compose ls` wypierałby `compose up`.
 *
 * Prawdziwej usługi pilnuje osobny zestaw testów (`BackgroundProcessServiceTest`)
 * i tam procesy są prawdziwe — bo tam właśnie one są tematem. Tam też stoi
 * różnica, której ta atrapa świadomie nie powtarza: prawdziwy port posuwa prace
 * w `pump()`, a nie w `poll()`. Tutaj posuwa je doglądanie, bo liczba doglądań
 * jest w tej atrapie **miarą czasu** — a każdy jej dzisiejszy użytkownik dogląda
 * swojej pracy raz na klatkę, czyli tak samo, jak pompuje je pętla.
 */
final class StubBackgroundProcess implements BackgroundProcessPort
{
    /** @var list<string> polecenia, o które poproszono — w kolejności */
    public array $startedCommands = [];

    /** @var list<int> limity czasu podane przy uruchamianiu */
    public array $timeouts = [];

    public int $stopCount = 0;

    /** @var array<int, BackgroundState> stan każdej pracy — numer uchwytu → stan */
    private array $states = [];

    /** @var array<int, int> ile razy doglądano każdej pracy */
    private array $polls = [];

    private int $lastId = 0;

    public function __construct(
        /** Po ilu doglądaniach praca się kończy. */
        private readonly int $pollsUntilDone = 2,
        private readonly string $output = "4096\t/home",
        private readonly int $exitCode = 0,
        /** Klucz powodu; ustawiony — praca nie rusza w ogóle. */
        private readonly ?string $problemKey = null,
        /** Strumień błędów polecenia — od kroku 49 port niesie go osobno. */
        private readonly string $errorOutput = '',
    ) {
    }

    public function start(string $command, int $timeoutSeconds): BackgroundHandle
    {
        $this->startedCommands[] = $command;
        $this->timeouts[] = $timeoutSeconds;

        $handle = new BackgroundHandle(++$this->lastId);
        $this->polls[$handle->id] = 0;
        $this->states[$handle->id] = $this->problemKey === null
            ? BackgroundState::running()
            : BackgroundState::failed($this->problemKey);

        return $handle;
    }

    public function poll(BackgroundHandle $handle): BackgroundState
    {
        $state = $this->states[$handle->id] ?? null;

        if ($state === null) {
            return BackgroundState::idle();
        }

        if (!$state->isRunning()) {
            return $state;
        }

        $polls = ++$this->polls[$handle->id];

        return $this->states[$handle->id] = $polls >= $this->pollsUntilDone
            ? BackgroundState::done($this->output, $this->exitCode, $this->errorOutput)
            : BackgroundState::running();
    }

    /**
     * Zapomina o wszystkich pracach — atrapa portu, który danego uchwytu **już
     * nie zna**.
     *
     * Stan osiągalny w prawdziwym porcie na dwa sposoby: pracę zatrzymał ktoś,
     * kto trzyma jej uchwyt, albo jej stan wypadł z zapasu pamiętanych prac
     * zakończonych. Do kroku 51 dochodziło się tu trzecią drogą — wyparciem
     * przez cudze zamówienie — i tamtej drogi już nie ma.
     */
    public function forgetEverything(): void
    {
        $this->states = [];
        $this->polls = [];
    }

    public function stop(BackgroundHandle $handle): void
    {
        if (!isset($this->states[$handle->id])) {
            return;
        }

        ++$this->stopCount;
        unset($this->states[$handle->id], $this->polls[$handle->id]);
    }
}
