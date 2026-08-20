<?php

declare(strict_types=1);

namespace LightManager\Examples\ZadanieKwerenda\Rozwiazanie\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\Generation;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Examples\ZadanieKwerenda\Rozwiazanie\Presentation\CzasModule;

/**
 * `czas.dzialanie` — **rozwiązanie zadania ćwiczebnego z onboardingu**
 * (`docs/pl/onboarding/04-pierwsza-zmiana.md`).
 *
 * Wobec pliku startowego ([`start/`](../../../start/)) zmieniło się **jedno
 * ciało metody** — `ask()`. Reszta stała tam gotowa.
 *
 * Cztery rzeczy, których to zadanie uczy, po kolei:
 *
 * 1. **Kwerenda czyta i nie zmienia.** To jest cała jej definicja i jedyny
 *    powód, dla którego wolno ją zadać z każdego miejsca. Zapis idzie komendą.
 * 2. **Pokolenie jest licznikiem zmian, nie znacznikiem czasu.** Gdyby
 *    `generation()` oddawało `microtime()`, rejestr przeliczałby tę kwerendę
 *    co klatkę — kilkadziesiąt razy na sekundę po wartość, która zmienia się
 *    raz na sekundę. Znacznikiem są więc **pełne sekundy**, i to jest cały
 *    powód, dla którego `seconds()` zaokrągla.
 * 3. **Czas przychodzi z zewnątrz.** Moment startu i chwila bieżąca są
 *    argumentami konstruktora, nie wywołaniem `microtime()` w środku.
 *    Kwerenda z własnym zegarem nie da się przetestować, bo nie da się jej
 *    powiedzieć, która jest godzina.
 * 4. **Jedna wartość ma swoją postać wyniku.** `QueryResult::value()` oddaje
 *    jeden wiersz o jednym polu; `of()`, `lazy()`, `owned()` i `failed()`
 *    odpowiadają na inne pytania — spis w przewodniku, sekcja „Nowa kwerenda”.
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

    public function ask(CommandInput $input): QueryResult
    {
        return QueryResult::value('seconds', $this->seconds());
    }

    /** Ile pełnych sekund minęło od startu; ujemnej odpowiedzi nie ma. */
    private function seconds(): int
    {
        $now = ($this->now)();
        assert(is_float($now));

        return max(0, (int) floor($now - $this->started));
    }
}
