<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Module\Kubernetes\Application\ClusterActions;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Presentation\ClusterScreen;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `k8s.apply [ścieżka]` — zastosowanie manifestu (krok 52, D91 nr 9).
 *
 * **Czynność, na której stanie krok 53**: wdrożenie obrazu zbudowanego Dockerem
 * kończy się właśnie tutaj. Mieszka raz i ma dwa wejścia (reguła 11n) —
 * klawisz `F5` na ekranie i tę komendę.
 *
 * Argument jest **nieobowiązkowy** i to jest cała różnica między dwoma
 * wejściami: podany — czynność rusza od razu, pominięty — otwiera się pole
 * tekstowe z propozycją z kontekstu przeglądarki (ta sama droga, którą moduł
 * Dockera bierze ścieżkę pliku compose, D90 nr 5).
 *
 * Ścieżką, **nigdy wejściem standardowym** — `kubectl apply -f -` jest
 * niewykonalne, bo port pracy tłowej nie podaje potomkowi wejścia.
 */
final class ApplyCommand implements CommandInterface, OpensOverlay
{
    private const PATH = 'path';

    public function __construct(
        private readonly ClusterScreen $screen,
        private readonly ClusterActions $actions,
    ) {
    }

    public function name(): string
    {
        return KubernetesSettings::ID . '.apply';
    }

    public function descriptionKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.command.apply';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::PATH,
                'module.' . KubernetesSettings::ID . '.argument.path',
                required: false,
            ),
        ];
    }

    public function overlayFor(CommandInput $input): ?OverlayOutcome
    {
        // Ścieżka podana w wierszu polecenia znaczy „wiem, co robię” — okno
        // z tą samą ścieżką wpisaną byłoby wtedy pytaniem o zgodę na to, o co
        // użytkownik właśnie poprosił.
        return trim($input->text(self::PATH)) === ''
            ? OverlayOutcome::replace($this->screen->openApplyPrompt())
            : null;
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $path = trim($input->text(self::PATH));

        if ($path !== '') {
            $this->actions->apply($path);
        }

        return CommandOutcome::opens(ClusterScreen::ID);
    }
}
