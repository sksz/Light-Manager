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
 * `docker.images` — otwiera listę obrazów (krok 51).
 *
 * Bliźniacza wobec `docker.ps` i osobna z jednego powodu: obrazy są **drugą
 * postacią tego samego ekranu**, więc bez własnej komendy dałoby się do nich
 * dojść wyłącznie klawiszem `F3` po otwarciu kontenerów. Nazwa komendy jest przy
 * tym tym, czego użytkownik szuka w oknie komend — „images”, a nie „ps, potem
 * F3”.
 */
final class ImagesCommand implements CommandInterface
{
    public function __construct(private readonly DockerScreen $screen)
    {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.images';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.command.images';
    }

    public function arguments(): array
    {
        return [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $this->screen->show(DockerView::Images);

        return CommandOutcome::opens(DockerScreen::ID);
    }
}
