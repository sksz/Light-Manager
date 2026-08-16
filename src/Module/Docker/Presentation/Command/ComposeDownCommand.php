<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Presentation\ComposeFlow;
use LightManager\Module\Docker\Presentation\DockerScreen;

/**
 * `docker.down [plik]` — kładzie projekt compose (krok 51).
 *
 * Bliźniacza wobec `docker.up` i **nie pyta o potwierdzenie**, choć zatrzymuje
 * kontenery: `down` nie kasuje ani obrazów, ani wolumenów nazwanych, więc
 * czynność jest odwracalna tym samym `up`, którym się ją cofa. Pytanie przed
 * czynnością odwracalną uczyłoby użytkownika odklikiwać pytania bez czytania —
 * a wtedy przestaje działać to jedno, które naprawdę ostrzega.
 */
final class ComposeDownCommand implements CommandInterface
{
    private const ARGUMENT = 'file';

    public function __construct(
        private readonly ComposeFlow $compose,
        private readonly DockerScreen $screen,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.down';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.command.down';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . DockerSettings::ID . '.argument.file',
                CommandArgumentKind::Path,
                required: false,
            ),
        ];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $message = $this->compose->down(
            $input->has(self::ARGUMENT) ? $input->text(self::ARGUMENT) : $this->screen->contextPath(),
        );

        return CommandOutcome::opens(DockerScreen::ID, $message);
    }
}
