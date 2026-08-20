<?php

declare(strict_types=1);

namespace LightManager\Examples\ModulPrzykladowy\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Query\Generation;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Examples\ModulPrzykladowy\PrzykladSettings;

/**
 * `przyklad.stan` — wzorzec kwerendy dla przewodnika „Nowa kwerenda”
 * (`docs/pl/przewodnik/03-jak-dodac.md`).
 *
 * Cztery rzeczy do przepisania do własnej kwerendy:
 *
 * 1. **Kwerenda czyta i nie zmienia.** To jest cała jej definicja i jedyny
 *    powód, dla którego wolno ją zadać z każdego miejsca (reguła 11w). Zapis
 *    idzie komendą, nigdy tędy.
 * 2. **Pokolenie jest prawdziwym licznikiem zmian**, a nie znacznikiem czasu.
 *    `Generation` bije je wtedy, gdy zmienia się to, co kwerenda oddaje — dzięki
 *    temu rejestr wie, że odpowiedź w pamięci jest jeszcze dobra, i nie liczy
 *    jej co klatkę.
 * 3. **Wiersze składa domknięcie, nie konstruktor.** Kwerenda zadana i niezadana
 *    kosztuje wtedy tyle samo: dopóki nikt nie pyta, nie powstaje ani jeden
 *    wiersz.
 * 4. **Ładunek typowany jest tylko dla właściciela.** `QueryResult::owned()`
 *    oddaje obiekt temu, kto zna jego klasę, a wszystkim pozostałym — wiersze.
 *    Moduł, który sięgnąłby po cudzy ładunek, sięgnąłby po cudzy typ, a tego
 *    zabrania reguła 15.
 */
final class StanQuery implements QueryInterface
{
    private readonly Generation $generation;

    public function __construct(
        /** Domknięcie, bo ustawienia zmieniają się między klatkami (patrz `generation()`). */
        private readonly \Closure $settings,
    ) {
        $this->generation = new Generation();
    }

    public function name(): string
    {
        return PrzykladSettings::ID . '.stan';
    }

    public function descriptionKey(): string
    {
        return PrzykladSettings::key('query.stan');
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->generation->of($this->ton());
    }

    public function ask(CommandInput $input): QueryResult
    {
        $ton = $this->ton();

        return QueryResult::lazy(static fn (): array => [['ton' => $ton]]);
    }

    private function ton(): string
    {
        $settings = ($this->settings)();
        assert($settings instanceof Settings);

        return PrzykladSettings::mowiGlosno($settings)
            ? PrzykladSettings::TON_GLOSNY
            : PrzykladSettings::TON_ZWYKLY;
    }
}
