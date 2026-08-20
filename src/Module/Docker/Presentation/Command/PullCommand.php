<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Presentation\PullFlow;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `docker.pull` — pobranie obrazu z rejestru (krok 61, etap 3).
 *
 * Bliźniak `PushCommand`, tym samym wzorcem: komenda niczego nie robi sama,
 * tylko prowadzi w `PullFlow`, czyli tam, gdzie prowadzi klawisz (11n).
 *
 * Różni się od tamtej jedną rzeczą i wynika ona z kierunku: **`execute()` bez
 * argumentu nie ma czego pobrać**. Przy wypchnięciu obraz brał się z listy —
 * leży u demona i widać go; obrazu, którego jeszcze nie ma lokalnie, nie da się
 * wskazać kursorem, więc nazwa musi paść wprost.
 */
final class PullCommand implements CommandInterface, OpensOverlay
{
    private const ARGUMENT = 'image';

    public function __construct(
        private readonly PullFlow $pulls,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return DockerSettings::ID . '.pull';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.command.pull';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . DockerSettings::ID . '.argument.image',
                required: false,
            ),
        ];
    }

    public function overlayFor(CommandInput $input): OverlayOutcome
    {
        return $this->pulls->request($input->text(self::ARGUMENT));
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $image = $input->text(self::ARGUMENT);

        if ($image === '') {
            return CommandOutcome::done(Message::warning(
                $this->translator->translate('module.' . DockerSettings::ID . '.pull.noImage'),
            ));
        }

        $this->pulls->begin($image);

        return CommandOutcome::done(Message::info(
            $this->translator->translate('module.' . DockerSettings::ID . '.pull.started', ['tag' => $image]),
        ));
    }
}
