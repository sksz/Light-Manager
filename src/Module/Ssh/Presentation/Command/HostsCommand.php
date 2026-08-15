<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Module\Ssh\Application\SshSettings;

/**
 * `ssh.hosts` — otwiera spis hostów (krok 48).
 *
 * Druga droga do tego samego, co `Ctrl`+`S`, i istnieje z tego samego powodu, co
 * `file-info.show`: skrót trzeba znać, a komendę można znaleźć w oknie komend
 * i w menu `F9`.
 *
 * Ekran wskazuje **identyfikatorem**, a nie obiektem — kontrakt komendy leży
 * w `Application` i o typach z `Presentation` nie wie (krok 19, D39). Rozwiązanie
 * identyfikatora na ekran należy do tego, kto komendę wykonuje.
 */
final class HostsCommand implements CommandInterface
{
    public function name(): string
    {
        return SshSettings::ID . '.hosts';
    }

    public function descriptionKey(): string
    {
        return 'module.' . SshSettings::ID . '.command.hosts';
    }

    public function arguments(): array
    {
        return [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::opens(SshSettings::ID);
    }
}
