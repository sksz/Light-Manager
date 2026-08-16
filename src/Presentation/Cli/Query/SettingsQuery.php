<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Dto\SettingKey;
use LightManager\Application\Query\Generation;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Presentation\Cli\LoopState;

/**
 * `core.settings` — komplet ustawień rdzenia wraz z wartościami.
 *
 * Pierwsza kwerenda rdzenia w tym projekcie i wzór dla pozostałych dwunastu.
 * Trzy rzeczy, które powtarzają się w każdej z nich:
 *
 * - **kwerenda rdzenia mieszka w `Presentation/Cli/Query`**, z tego samego
 *   powodu, dla którego mieszkają tam komendy rdzenia: dostaje stan pętli
 *   i usługi, których `Application` nie ma prawa znać;
 * - **pokolenie bierze się z taniego znacznika** — tutaj z tożsamości obiektu
 *   ustawień, bo `LoopState::applySettings()` wymienia go przy każdej zmianie,
 *   a porównanie wskaźników kosztuje tyle, co nic;
 * - **wiersze budują się leniwie** — `QueryResult::lazy()` — więc pytanie
 *   o pokolenie nie płaci za przejście po kluczach.
 */
final class SettingsQuery implements QueryInterface
{
    private readonly Generation $generation;

    public function __construct(
        private readonly LoopState $state,
    ) {
        $this->generation = new Generation();
    }

    public function name(): string
    {
        return 'core.settings';
    }

    public function descriptionKey(): string
    {
        return 'query.core.settings';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->generation->of($this->state->settings());
    }

    public function ask(CommandInput $input): QueryResult
    {
        $settings = $this->state->settings();

        return QueryResult::lazy(static function () use ($settings): array {
            $rows = [];

            foreach (SettingKey::cases() as $key) {
                $rows[] = ['key' => $key->value, 'value' => $settings->valueOf($key)];
            }

            return $rows;
        });
    }
}
