<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\Ssh\Application\SshSession;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Presentation\SshQueries;

/**
 * `ssh.disconnect` — zamyka sesję (krok 48).
 *
 * Bez argumentu, bo sesja jest **jedna** (D87 nr 7) i nie ma czego wskazywać.
 * Okna nie potrzebuje: zamknięcie mistrza jest rozmową z gniazdem na dysku, nie
 * z siecią, więc nie ma tu pracy dłuższej od klatki, którą trzeba by pokazać.
 *
 * Rozłączenie bez sesji **nie jest błędem**, tylko zdaniem „nie ma czego
 * zamykać" — tak samo, jak `stop()` w porcie pracy tłowej wolno wołać zawsze.
 */
final class DisconnectCommand implements CommandInterface
{
    public function __construct(
        private readonly SshSession $session,
        private readonly SshQueries $reader,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return SshSettings::ID . '.disconnect';
    }

    public function descriptionKey(): string
    {
        return 'module.' . SshSettings::ID . '.command.disconnect';
    }

    public function arguments(): array
    {
        return [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $state = $this->reader->session();

        if (!$state->isConnected() || $state->host === null) {
            return CommandOutcome::done(Message::info(
                $this->translator->translate('module.' . SshSettings::ID . '.message.nothing'),
            ));
        }

        $label = $state->host->label();
        $this->session->disconnect();

        return CommandOutcome::done(Message::info(
            $this->translator->translate('module.' . SshSettings::ID . '.message.disconnected', ['host' => $label]),
        ));
    }
}
