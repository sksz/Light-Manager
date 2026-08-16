<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Query;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Module\Browser\Application\BrowserSettings;

/**
 * Numer panelu jako argument kwerendy — wspólny dla czterech z sześciu kwerend
 * przeglądarki (krok 53).
 *
 * Przeglądarka jest jedynym modułem, który pokazuje **dwa miejsca naraz**
 * (krok 24), więc jest też jedynym, którego kwerendy muszą pytać „którego”.
 * `ModuleContext` mówi wyłącznie o panelu czynnym i to się nie zmienia — kwerenda
 * jest jedyną drogą do tego drugiego.
 *
 * Brak argumentu znaczy **panel z ogniskiem**, a nie pierwszy: pytający bez
 * zdania na ten temat pyta o to, na co użytkownik właśnie patrzy.
 */
final class PaneArgument
{
    /** Nazwa, pod którą kwerenda odbiera wartość. */
    public const NAME = 'pane';

    /** Wartość znacząca „ten z ogniskiem”. */
    private const FOCUSED = -1;

    private function __construct()
    {
    }

    public static function declaration(): CommandArgument
    {
        return new CommandArgument(
            self::NAME,
            'module.' . BrowserSettings::ID . '.query.argument.pane',
            CommandArgumentKind::Number,
            required: false,
        );
    }

    /** Numer panelu z wiersza; `null` — pytający nie wskazał żadnego. */
    public static function from(CommandInput $input): ?int
    {
        if (!$input->has(self::NAME)) {
            return null;
        }

        $index = $input->number(self::NAME, self::FOCUSED);

        return $index === self::FOCUSED ? null : ($index === 1 ? 1 : 0);
    }
}
