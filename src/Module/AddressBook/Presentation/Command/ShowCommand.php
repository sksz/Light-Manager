<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Command;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Module\AddressBook\Application\AddressBookSettings;

/**
 * `address-book.show` — otwiera książkę (krok 60).
 *
 * Druga droga do tego, co `Ctrl`+`W`, i istnieje z tego samego powodu, co
 * `file-info.show`: skrót trzeba znać, a komendę można znaleźć w oknie komend
 * i w menu `F9`.
 */
final class ShowCommand implements CommandInterface
{
    public function name(): string
    {
        return AddressBookSettings::ID . '.show';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AddressBookSettings::ID . '.command.show';
    }

    public function arguments(): array
    {
        return [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        return CommandOutcome::opens(AddressBookSettings::ID);
    }
}
