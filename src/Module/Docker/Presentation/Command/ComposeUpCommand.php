<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\Environments;
use LightManager\Module\Docker\Presentation\ComposeFlow;
use LightManager\Module\Docker\Presentation\DockerScreen;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\Overlay\ConfirmOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `docker.up [plik]` — podnosi projekt compose (krok 51).
 *
 * Plik jest **opcjonalny** i bez niego bierze się z kontekstu przeglądarki
 * (D90 nr 5): `compose.yaml` leży zwykle w katalogu, w którym stoi użytkownik.
 * Praca trwa minutami, więc komenda jej nie czeka — ekran pokazuje, co się
 * dzieje, a koniec ogłasza zdarzenie.
 *
 * **W środowisku zdalnym komenda pyta, zanim podniesie** (krok 58, punkt 6
 * planu — pułapka nazwana w napisach, nie w komentarzu): plik compose czyta
 * **klient**, więc leży po stronie lokalnej — ale `volumes:` z montowaniem
 * katalogu wskazują ścieżki **po stronie demona**, a kontekst budowy jedzie
 * przez sieć. Compose lokalnego pliku przeciwko zdalnemu demonowi działa
 * i **nie znaczy tego samego**, co lokalnie; użytkownik dowiaduje się o tym
 * zdaniem przed podniesieniem, a nie z zachowania kontenerów.
 */
final class ComposeUpCommand implements CommandInterface, OpensOverlay
{
    private const ARGUMENT = 'file';

    public function __construct(
        private readonly ComposeFlow $compose,
        private readonly DockerScreen $screen,
        private readonly Environments $environments,
        private readonly TranslatorPort $translator,
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
        $message = $this->compose->up($this->pathFrom($input));

        return CommandOutcome::opens(DockerScreen::ID, $message);
    }

    /**
     * Okno pytania — wyłącznie w środowisku zdalnym; przy gnieździe lokalnym
     * komenda wykonuje się zwyczajnie (`null`).
     */
    public function overlayFor(CommandInput $input): ?OverlayOutcome
    {
        if (!$this->environments->isRemote()) {
            return null;
        }

        $path = $this->pathFrom($input);

        return OverlayOutcome::replace(new ConfirmOverlay(
            'module.' . DockerSettings::ID . '.compose.remoteWarning',
            ['name' => $this->environments->currentName()],
            fn (): OverlayOutcome => OverlayOutcome::close($this->compose->up($path)),
            $this->translator,
        ));
    }

    private function pathFrom(CommandInput $input): string
    {
        return $input->has(self::ARGUMENT) ? $input->text(self::ARGUMENT) : $this->screen->contextPath();
    }
}
