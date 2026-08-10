<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

use LightManager\Domain\ValueObject\Message;

/**
 * Wynik rozbioru wpisanego wiersza: komenda gotowa do wywołania albo powód,
 * dla którego nie da się jej wywołać.
 *
 * Powód jest **gotowym komunikatem**, a nie kodem błędu, bo składa go parser —
 * jedyne miejsce, które wie, którego argumentu zabrakło i czego się spodziewano.
 * Dzięki temu wszystkie komendy tłumaczą się użytkownikowi tym samym zdaniem.
 */
final class CommandLine
{
    private function __construct(
        public readonly ?CommandInterface $command,
        public readonly ?CommandInput $input,
        public readonly ?Message $problem,
    ) {
    }

    public static function of(CommandInterface $command, CommandInput $input): self
    {
        return new self($command, $input, null);
    }

    public static function problem(Message $message): self
    {
        return new self(null, null, $message);
    }

    public function isValid(): bool
    {
        return $this->command !== null && $this->input !== null;
    }
}
