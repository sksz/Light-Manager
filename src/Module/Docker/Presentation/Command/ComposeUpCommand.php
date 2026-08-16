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
 * `docker.up [plik]` — podnosi projekt compose (krok 51).
 *
 * Plik jest **opcjonalny** i bez niego bierze się z kontekstu przeglądarki
 * (D90 nr 5): `compose.yaml` leży zwykle w katalogu, w którym stoi użytkownik.
 * Praca trwa minutami, więc komenda jej nie czeka — ekran pokazuje, co się
 * dzieje, a koniec ogłasza zdarzenie.
 */
final class ComposeUpCommand implements CommandInterface
{
    private const ARGUMENT = 'file';

    public function __construct(
        private readonly ComposeFlow $compose,
        private readonly DockerScreen $screen,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.up';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.command.up';
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
        $message = $this->compose->up(
            $input->has(self::ARGUMENT) ? $input->text(self::ARGUMENT) : $this->screen->contextPath(),
        );

        return CommandOutcome::opens(DockerScreen::ID, $message);
    }
}
