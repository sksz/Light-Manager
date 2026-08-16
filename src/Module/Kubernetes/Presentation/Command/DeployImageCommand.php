<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Presentation\ClusterScreen;
use LightManager\Module\Kubernetes\Presentation\DeployImageFlow;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `k8s.deploy-image` — **czynność, dla której powstała cała Faza XVIII**
 * (krok 54, D85).
 *
 * Miara powodzenia kroku brzmi: *pokazuje listę obrazów, które zna moduł Dockera,
 * buduje wskazany, czeka na koniec budowy i podmienia obraz w wybranym wdrożeniu
 * — a przy wyłączonym module Dockera ta sama czynność mówi, czego brakuje,
 * zamiast się wywrócić.*
 *
 * Komenda sama nie robi nic prócz otwarcia pierwszego okna: choreografia mieszka
 * w `DeployImageFlow`, bo ma **dwa wejścia** — tę komendę i pozycję w menu `F9`,
 * którą dostaje za darmo dzięki `OpensOverlay` z kroku 47 (11n).
 *
 * Argumentów **nie ma i nie będzie**: każdy etap wybiera się z listy, którą
 * dopiero trzeba zobaczyć, a nazwa obrazu wpisana z pamięci przed pierwszym
 * pytaniem oszczędzałaby jedno naciśnięcie kosztem wszystkich pozostałych.
 */
final class DeployImageCommand implements CommandInterface, OpensOverlay
{
    public function __construct(
        private readonly DeployImageFlow $flow,
    ) {
    }

    public function name(): string
    {
        return KubernetesSettings::ID . '.deploy-image';
    }

    public function descriptionKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.command.deployImage';
    }

    public function arguments(): array
    {
        return [];
    }

    public function overlayFor(CommandInput $input): OverlayOutcome
    {
        return $this->flow->begin();
    }

    /**
     * Wykonanie bez okna **nie zdarza się** — `overlayFor()` oddaje okno zawsze,
     * a przy wyłączonym module Dockera zdanie o tym, czego brakuje. Metoda
     * zostaje, bo wymaga jej kontrakt komendy.
     */
    public function execute(CommandInput $input): CommandOutcome
    {
        $this->flow->begin();

        return CommandOutcome::opens(ClusterScreen::ID);
    }
}
