<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Kubernetes\Application\ApiCatalog;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Presentation\ClusterScreen;

/**
 * `k8s.get <rodzaj>` — otwiera ekran na liście wskazanego rodzaju (krok 52).
 *
 * Komenda jest **drugą drogą do tego, co robi drzewo**, i istnieje z powodu,
 * który przy kilkudziesięciu rodzajach waży więcej niż zwykle: do `pods` dojdzie
 * się drzewem w trzy naciśnięcia, ale do `poddisruptionbudgets.policy` —
 * przewijając grupę, której nazwy trzeba się domyślić. Wpisanie nazwy jest
 * wtedy krótszą drogą, a nazwę tę użytkownik zna z `kubectl`.
 *
 * Rodzaj rozpoznajemy **po nazwie mnogiej, po skrócie i po adresie** — czyli tak,
 * jak rozpoznaje go sam `kubectl`: `po`, `pods` i `pods` znaczą to samo,
 * a `events.events.k8s.io` rozstrzyga niejednoznaczność.
 */
final class GetCommand implements CommandInterface
{
    private const KIND = 'kind';

    public function __construct(
        private readonly ClusterScreen $screen,
        private readonly ApiCatalog $catalog,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return KubernetesSettings::ID . '.get';
    }

    public function descriptionKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.command.get';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::KIND,
                'module.' . KubernetesSettings::ID . '.argument.kind',
            ),
        ];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $wanted = trim($input->text(self::KIND));

        foreach ($this->catalog->kinds() as $kind) {
            if ($wanted === $kind->name || $wanted === $kind->address() || in_array($wanted, $kind->shortNames, true)) {
                $this->screen->show($kind);

                return CommandOutcome::opens(ClusterScreen::ID);
            }
        }

        // Katalog bywa pusty nie dlatego, że rodzaju nie ma, tylko dlatego, że
        // nikt jeszcze nie otworzył ekranu — i to jest inna wiadomość dla
        // użytkownika niż „nie ma takiego rodzaju”.
        return CommandOutcome::stay(Message::warning($this->translator->translate(
            'module.' . KubernetesSettings::ID . ($this->catalog->isLoaded() ? '.command.noKind' : '.command.noCatalog'),
            ['kind' => $wanted],
        )));
    }
}
