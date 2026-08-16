<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Presentation\BuildFlow;
use LightManager\Module\Docker\Presentation\DockerScreen;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `docker.build [katalog]` — buduje obraz (krok 51).
 *
 * Katalog jest **opcjonalny**: bez niego otwiera się to samo okno, co pod `F7`,
 * z katalogiem przeglądarki jako treścią początkową; z nim łańcuch zaczyna się
 * od pytania o nazwę obrazu. Wzorzec ten sam, co w `ssh.get` — komenda niczego
 * nie robi sama, tylko prowadzi w `BuildFlow`, czyli tam, gdzie prowadzi klawisz
 * (reguła 11n).
 *
 * **Okno otwiera przez `OpensOverlay`** — zdolność spłacona w kroku 47 właśnie
 * po to, żeby komenda nie musiała otwierać ekranu i liczyć, że ten sam się
 * domyśli. Kontekst komenda bierze z ekranu modułu, bo to on go czyta
 * (`ReadsContext`).
 */
final class BuildCommand implements CommandInterface, OpensOverlay
{
    private const ARGUMENT = 'directory';

    public function __construct(
        private readonly BuildFlow $builds,
        private readonly DockerScreen $screen,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.build';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.command.build';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . DockerSettings::ID . '.argument.directory',
                CommandArgumentKind::Path,
                required: false,
            ),
        ];
    }

    public function overlayFor(CommandInput $input): OverlayOutcome
    {
        return $this->builds->request($this->directoryFrom($input));
    }

    /**
     * Wykonanie bez okna **nie zdarza się** — `overlayFor()` oddaje okno zawsze,
     * a przy trwającej budowie zdanie o tym, że coś już się buduje. Metoda
     * zostaje, bo wymaga jej kontrakt komendy; gdyby ktoś ją zawołał, zachowa
     * się tak samo, jak droga przez okno.
     */
    public function execute(CommandInput $input): CommandOutcome
    {
        $this->builds->request($this->directoryFrom($input));

        return CommandOutcome::opens(DockerScreen::ID);
    }

    private function directoryFrom(CommandInput $input): string
    {
        return $input->has(self::ARGUMENT) ? $input->text(self::ARGUMENT) : $this->screen->contextPath();
    }
}
