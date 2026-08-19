<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Domain\Exception\InvalidAddressEntryException;
use LightManager\Module\AddressBook\Presentation\AddressBookQueries;
use LightManager\Module\AddressBook\Presentation\Suggestions;

/**
 * `address-book.rename <wpis> <nazwa>` — zmiana nazwy wpisu (krok 60).
 *
 * Komenda, której w książce usuniętej w kroku poprzednim **nie mogło być**,
 * dopóki tożsamością była nazwa: zmiana nazwy psułaby po cichu każde cudze
 * odniesienie. Skoro tożsamością jest identyfikator, nazwa jest **zwykłym
 * polem** — wolno ją zmienić, powtórzyć i zostawić pustą, a odniesienia tego
 * nie zauważą.
 */
final class RenameCommand implements CommandInterface, SuggestsArguments
{
    public const ENTRY = 'entry';

    public const NAME = 'name';

    public function __construct(
        private readonly Addresses $addresses,
        private readonly AddressBookQueries $reader,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.rename';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('command.rename');
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ENTRY,
                AddressBookSettings::key('argument.entry'),
                suggestions: SuggestionSource::OnDemand,
            ),
            new CommandArgument(self::NAME, AddressBookSettings::key('argument.name'), required: false),
        ];
    }

    public function suggestions(string $argument, string $prefix): array
    {
        return $argument === self::ENTRY ? Suggestions::entries($this->reader, $prefix) : [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $id = Suggestions::idOf($input->text(self::ENTRY));
        $name = trim($input->text(self::NAME));

        try {
            $renamed = $this->addresses->rename($id, $name);
        } catch (InvalidAddressEntryException $exception) {
            return CommandOutcome::done(Message::error($this->translator->translate(
                $exception->problemKey(),
                $exception->problemParameters(),
            )));
        }

        if (!$renamed) {
            return CommandOutcome::done(Message::error(
                $this->translator->translate(AddressBookSettings::key('message.unknown'), ['entry' => $id]),
            ));
        }

        return CommandOutcome::done(Message::info(
            $this->translator->translate(AddressBookSettings::key('message.renamed'), [
                'id' => $id,
                'name' => $name,
            ]),
        ));
    }
}
