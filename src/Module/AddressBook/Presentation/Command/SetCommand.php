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
 * `address-book.set <wpis> <rozdział> <pole> <wartość>` — zapis jednej wartości
 * (krok 60).
 *
 * **Rozdział jest argumentem, nie przegrodą** (D104 nr 1): tą jedną komendą
 * pisze moduł Ssh po rozdziale `ssh`, moduł Dockera po `docker`, ekran książki
 * po dowolnym, a użytkownik z okna komend po wszystkich. Nikt nie musi rozdziału
 * deklarować, żeby móc w nim zapisać.
 *
 * Rozdział **zadeklarowany** sprawdza rodzaj wartości (D105 nr 3);
 * **niezadeklarowany** przyjmuje ją jako napis — bo brak deklaracji nie zabiera
 * dostępu, więc nie może też zabierać zapisu.
 */
final class SetCommand implements CommandInterface, SuggestsArguments
{
    public const ENTRY = 'entry';

    public const CHAPTER = 'chapter';

    public const FIELD = 'field';

    public const VALUE = 'value';

    public function __construct(
        private readonly Addresses $addresses,
        private readonly AddressBookQueries $reader,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.set';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('command.set');
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ENTRY,
                AddressBookSettings::key('argument.entry'),
                suggestions: SuggestionSource::OnDemand,
            ),
            new CommandArgument(
                self::CHAPTER,
                AddressBookSettings::key('argument.chapter'),
                suggestions: SuggestionSource::OnDemand,
            ),
            new CommandArgument(
                self::FIELD,
                AddressBookSettings::key('argument.field'),
                suggestions: SuggestionSource::OnDemand,
            ),
            new CommandArgument(self::VALUE, AddressBookSettings::key('argument.value'), required: false),
        ];
    }

    public function suggestions(string $argument, string $prefix): array
    {
        return match ($argument) {
            self::ENTRY => Suggestions::entries($this->reader, $prefix),
            self::CHAPTER => Suggestions::chapters($this->reader, $prefix),
            default => [],
        };
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $id = Suggestions::idOf($input->text(self::ENTRY));
        $chapter = trim($input->text(self::CHAPTER));
        $field = trim($input->text(self::FIELD));

        try {
            $written = $this->addresses->set($id, $chapter, $field, trim($input->text(self::VALUE)));
        } catch (InvalidAddressEntryException $exception) {
            return CommandOutcome::done(Message::error($this->translator->translate(
                $exception->problemKey(),
                $exception->problemParameters(),
            )));
        }

        if (!$written) {
            return CommandOutcome::done(Message::error(
                $this->translator->translate(AddressBookSettings::key('message.unknown'), ['entry' => $id]),
            ));
        }

        return CommandOutcome::done(Message::info(
            $this->translator->translate(AddressBookSettings::key('message.set'), [
                'chapter' => $chapter,
                'field' => $field,
            ]),
        ));
    }
}
