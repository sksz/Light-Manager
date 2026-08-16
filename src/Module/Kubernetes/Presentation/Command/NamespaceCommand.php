<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Presentation\ClusterScreen;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `k8s.namespace` — zmiana przestrzeni nazw (krok 52).
 *
 * Pole tekstowe, a nie lista, i jest to wybór świadomy: spis przestrzeni to
 * osobne pytanie do klastra, a użytkownik pracujący z klastrem zna nazwę swojej
 * przestrzeni na pamięć. Przestrzenie **da się przy tym obejrzeć jak każdy inny
 * rodzaj** — stoją w drzewie pod `core/namespaces`, więc lista istnieje i bez
 * dodatkowego wywołania.
 */
final class NamespaceCommand implements CommandInterface, OpensOverlay
{
    public function __construct(private readonly ClusterScreen $screen)
    {
    }

    public function name(): string
    {
        return KubernetesSettings::ID . '.namespace';
    }

    public function descriptionKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.command.namespace';
    }

    public function arguments(): array
    {
        return [];
    }

    /** Pole na nazwę przestrzeni — zawsze; komenda nie przyjmuje argumentu. */
    public function overlayFor(CommandInput $input): OverlayOutcome
    {
        return OverlayOutcome::replace($this->screen->openNamespacePrompt());
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::opens(ClusterScreen::ID);
    }
}
