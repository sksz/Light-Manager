<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Dto\SettingKey;
use LightManager\Application\Query\Generation;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryRegistry;
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
 * - **wiersze budują się leniwie**, więc pytanie o pokolenie nie płaci za
 *   przejście po kluczach.
 *
 * Ładunek typowany oddaje **cały obiekt ustawień** i to on jest drogą, którą
 * rdzeń czyta własną konfigurację: ekran ustawień dostaje `Settings`, a nie
 * wiersze napisów (D92 nr 3 — rejestr jest jedyną drogą odczytu **także
 * wewnątrz rdzenia**).
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

        return QueryResult::owned(QueryRegistry::CORE, $settings, static function () use ($settings): array {
            $rows = [];

            foreach (SettingKey::cases() as $key) {
                $rows[] = ['key' => $key->value, 'value' => $settings->valueOf($key)];
            }

            return $rows;
        });
    }
}
