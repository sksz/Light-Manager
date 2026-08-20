<?php

declare(strict_types=1);

namespace LightManager\Examples\ZadanieKwerenda\Start\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\Generation;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Examples\ZadanieKwerenda\Start\Presentation\CzasModule;

/**
 * `czas.dzialanie` — **plik startowy zadania ćwiczebnego z onboardingu**
 * (`docs/pl/onboarding/04-pierwsza-zmiana.md`).
 *
 * Rdzeń aplikacji umie dziś powiedzieć o sobie trzynaście rzeczy — wersję,
 * rozszerzenia, moduły, motyw, język, tor rysowania klatki. **Nie umie
 * powiedzieć, od jak dawna działa.** To jest ta jedna rzecz, którą dołożysz.
 *
 * Luka jest jedna i stoi w `ask()`. Wszystko poza nią jest gotowe — łącznie
 * z `generation()`, bo pokolenie jest w kwerendzie rzeczą najmniej oczywistą
 * i lepiej je zobaczyć zrobione niż zgadywać:
 *
 * **Pokolenie jest licznikiem zmian, nie znacznikiem czasu.** Rejestr pyta
 * o nie przed każdym odczytem i przelicza odpowiedź wyłącznie wtedy, gdy liczba
 * urosła. Gdyby stało tu `microtime()`, rejestr liczyłby tę kwerendę **co
 * klatkę** — czyli kilkadziesiąt razy na sekundę, żeby oddać wartość, która
 * zmienia się raz na sekundę. Dlatego znacznikiem są **pełne sekundy**.
 *
 * Drugi wzorzec do zapamiętania: **czas przychodzi z zewnątrz**. Kwerenda nie
 * woła `microtime()` sama — dostaje moment startu i domknięcie oddające chwilę
 * bieżącą. Kwerenda z własnym zegarem nie da się przetestować, bo nie da się
 * jej powiedzieć, która jest godzina.
 *
 * Rozwiązanie stoi obok: [`rozwiazanie/`](../../../rozwiazanie/). Zajrzyj do
 * niego dopiero wtedy, gdy utkniesz — albo na koniec, żeby porównać.
 */
final class CzasDzialaniaQuery implements QueryInterface
{
    private readonly Generation $generation;

    public function __construct(
        private readonly float $started,
        /** Chwila bieżąca — domknięciem, bo zegar w środku odbiera testowalność. */
        private readonly \Closure $now,
    ) {
        $this->generation = new Generation();
    }

    public function name(): string
    {
        return CzasModule::ID . '.dzialanie';
    }

    public function descriptionKey(): string
    {
        return 'module.' . CzasModule::ID . '.query.dzialanie';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->generation->of($this->seconds());
    }

    /**
     * **Tu jest zadanie.** Oddaj liczbę sekund działania jako odpowiedź
     * kwerendy — jedno pole, jedna wartość. Postać wyniku wybierasz z pięciu,
     * a ta, której szukasz, opisana jest w przewodniku jako „jedna wartość”:
     * `docs/pl/przewodnik/03-jak-dodac.md`, sekcja „Nowa kwerenda”.
     *
     * Liczbę masz gotową — oddaje ją `seconds()` poniżej. Nazwij pole `seconds`.
     */
    public function ask(CommandInput $input): QueryResult
    {
        return QueryResult::empty();
    }

    /** Ile pełnych sekund minęło od startu; ujemnej odpowiedzi nie ma. */
    private function seconds(): int
    {
        $now = ($this->now)();
        assert(is_float($now));

        return max(0, (int) floor($now - $this->started));
    }
}
