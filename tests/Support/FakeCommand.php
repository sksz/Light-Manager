<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\SuggestsArguments;

/**
 * Komenda na potrzeby testów: zapamiętuje, z czym ją wywołano, i oddaje z góry
 * ustalony skutek.
 */
final class FakeCommand implements CommandInterface, SuggestsArguments
{
    public ?CommandInput $received = null;

    /**
     * @param list<CommandArgument> $arguments
     * @param list<string>          $choices
     */
    public function __construct(
        private readonly string $name,
        private readonly array $arguments = [],
        private readonly array $choices = [],
        private readonly ?CommandOutcome $outcome = null,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function descriptionKey(): string
    {
        return 'command.' . $this->name;
    }

    public function arguments(): array
    {
        return $this->arguments;
    }

    public function suggestions(string $argument, string $prefix): array
    {
        return $this->choices;
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $this->received = $input;

        return $this->outcome ?? CommandOutcome::done();
    }
}
