<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Module\ModuleRegistry;
use LightManager\Application\Query\Generation;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Presentation\Cli\LoopState;

/**
 * `core.module-settings <moduł>` — wartości z podprzestrzeni `modules.<id>`.
 *
 * Osobno od `core.settings`, bo to są dwie różne rzeczy: rdzeń zna swoje klucze
 * z enuma i potrafi wypisać komplet, a kluczy modułu nie zna wcale — leżą
 * w pliku tak, jak je moduł zapisał. Kwerenda oddaje więc **to, co w pliku
 * jest**, a nie deklarację pozycji: moduł wyłączony ma swoje ustawienia
 * nietknięte i wolno o nie zapytać.
 */
final class ModuleSettingsQuery implements QueryInterface, SuggestsArguments
{
    private readonly Generation $generation;

    public function __construct(
        private readonly LoopState $state,
        private readonly ModuleRegistry $modules,
    ) {
        $this->generation = new Generation();
    }

    public function name(): string
    {
        return 'core.module-settings';
    }

    public function descriptionKey(): string
    {
        return 'query.core.module-settings';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument('module', 'query.argument.module', suggestions: SuggestionSource::Fixed),
        ];
    }

    public function generation(): int
    {
        return $this->generation->of($this->state->settings());
    }

    public function suggestions(string $argument, string $prefix): array
    {
        $ids = [];

        foreach ($this->modules->declared() as $module) {
            $ids[] = $module->id();
        }

        return $ids;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $values = $this->state->settings()->moduleSettings($input->text('module'));

        return QueryResult::lazy(static function () use ($values): array {
            $rows = [];

            foreach ($values as $key => $value) {
                $rows[] = ['key' => $key, 'value' => $value];
            }

            return $rows;
        });
    }
}
