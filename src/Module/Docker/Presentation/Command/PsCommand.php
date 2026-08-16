<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Presentation\DockerScreen;
use LightManager\Module\Docker\Presentation\DockerView;

/**
 * `docker.ps` — otwiera listę kontenerów (krok 51).
 *
 * Druga droga do tego samego, co `Ctrl`+`O`, i istnieje z tego samego powodu, co
 * `ssh.hosts`: skrót trzeba znać, a komendę można znaleźć w oknie komend.
 *
 * Ekran wskazuje **identyfikatorem**, a nie obiektem — kontrakt komendy leży
 * w `Application` i o typach z `Presentation` nie wie (krok 19, D39). Postać
 * ekranu przestawia się przy tym **przed** jego otwarciem: komenda nazywa się
 * „ps”, więc ma pokazać kontenery także wtedy, gdy ostatnio oglądano obrazy.
 */
final class PsCommand implements CommandInterface
{
    public function __construct(private readonly DockerScreen $screen)
    {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.ps';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.command.ps';
    }

    public function arguments(): array
    {
        return [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $this->screen->show(DockerView::Containers);

        return CommandOutcome::opens(DockerScreen::ID);
    }
}
