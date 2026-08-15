<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\NeedsTick;
use LightManager\Domain\Exception\DomainException;

/**
 * Takt modułów: jedno uderzenie na klatkę, dla każdego przyjętego modułu, który
 * o nie poprosił (krok 45).
 *
 * Klasa istnieje po to, żeby `GameLoop` urósł o **jedno** pole zamiast o listę
 * modułów i prezentera problemów naraz — a przede wszystkim po to, żeby reguła
 * „takt nie rzuca” miała jedno miejsce, w którym jest wykonana. Rozsypana po
 * pętli rozjechałaby się przy pierwszym module, który o niej zapomni.
 *
 * `DomainException` łapiemy tą samą regułą, co w drodze przez ekran
 * (`InputHandler`): użytkownik ma zobaczyć zdanie w pasku stanu, a nie ślad
 * stosu, a pętla ma się kręcić dalej. Wyjątek jednego modułu **nie zabiera taktu
 * pozostałym** — stąd `try` w środku pętli, a nie wokół niej.
 *
 * Modułów bez zdolności tu nie ma: odsiew robi `of()` raz, przy składaniu
 * aplikacji, a nie trzydzieści razy na sekundę.
 */
final class ModuleTicker
{
    /** @param list<NeedsTick> $modules */
    public function __construct(
        private readonly array $modules,
        private readonly ProblemPresenter $problems,
    ) {
    }

    /**
     * Odsiew modułów proszących o takt — **raz, przy składaniu aplikacji**.
     *
     * @param list<ModuleInterface> $modules moduły przyjęte przez rejestr
     */
    public static function of(array $modules, ProblemPresenter $problems): self
    {
        $ticking = [];

        foreach ($modules as $module) {
            if ($module instanceof NeedsTick) {
                $ticking[] = $module;
            }
        }

        return new self($ticking, $problems);
    }

    /** Czy ktokolwiek prosił o takt — pytanie dla testu i dla dziennika pomiaru. */
    public function isEmpty(): bool
    {
        return $this->modules === [];
    }

    public function tick(LoopState $state, float $now): void
    {
        foreach ($this->modules as $module) {
            try {
                $module->tick($now);
            } catch (DomainException $exception) {
                $state->reportProblem($this->problems->text($exception), $now);
            }
        }
    }
}
