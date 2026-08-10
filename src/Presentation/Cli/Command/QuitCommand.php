<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;

/**
 * `core.quit` — druga droga do tego, co robi `F10`.
 *
 * Istnieje nie dlatego, że `F10` jest niewygodne, tylko dlatego, że okno komend
 * ma od pierwszego dnia pokazywać komplet czynności rdzenia. Komenda, której
 * skutek widać natychmiast, jest przy okazji najprostszym sprawdzianem drogi
 * „wpisz nazwę → uruchom”.
 */
final class QuitCommand implements CommandInterface
{
    public function name(): string
    {
        return 'core.quit';
    }

    public function descriptionKey(): string
    {
        return 'command.core.quit';
    }

    public function arguments(): array
    {
        return [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::quit();
    }
}
