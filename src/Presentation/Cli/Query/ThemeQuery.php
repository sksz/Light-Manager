<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Port\ThemePort;
use LightManager\Application\Query\Generation;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Presentation\Cli\LoopState;

/**
 * `core.theme` — paleta czynna i lista tych, które są do wyboru.
 *
 * Kolorów kwerenda **nie oddaje** i nie jest to przeoczenie: rola motywu jest
 * pojęciem warstwy rysowania, a moduł dostaje ją klockiem — komponentem, który
 * sam wie, którą rolą się namalować (reguła 11). Kto pyta o motyw, pyta „czym
 * dziś jest jasne, a czym ciemne”, i tyle wystarczy.
 */
final class ThemeQuery implements QueryInterface
{
    private readonly Generation $generation;

    public function __construct(
        private readonly LoopState $state,
        private readonly ThemePort $themes,
    ) {
        $this->generation = new Generation();
    }

    public function name(): string
    {
        return 'core.theme';
    }

    public function descriptionKey(): string
    {
        return 'query.core.theme';
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->generation->of($this->state->settings()->theme);
    }

    public function ask(CommandInput $input): QueryResult
    {
        $active = $this->state->settings()->theme;
        $names = $this->themes->names();

        return QueryResult::lazy(static function () use ($active, $names): array {
            $rows = [];

            foreach ($names as $name) {
                $rows[] = ['name' => $name, 'active' => $name === $active];
            }

            return $rows;
        });
    }
}
