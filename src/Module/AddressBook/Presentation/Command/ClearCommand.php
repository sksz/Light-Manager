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
use LightManager\Module\AddressBook\Presentation\AddressBookQueries;
use LightManager\Module\AddressBook\Presentation\Suggestions;

/**
 * `address-book.clear <wpis> <rozdział> [pole]` — czyści jedno pole albo cały
 * rozdział **jednego** wpisu (krok 60).
 *
 * Bez pola czyści rozdział w całości i to jest zamierzone skrótem: wpis, który
 * przestał być hostem, przestaje nim być całym rozdziałem, a nie polem po polu.
 * Czyszczenie rozdziału **we wszystkich** wpisach to osobna czynność
 * (`address-book.forget`), bo dotyczy czegoś innego i pyta oknem.
 */
final class ClearCommand implements CommandInterface, SuggestsArguments
{
    public const ENTRY = 'entry';

    public const CHAPTER = 'chapter';

    public const FIELD = 'field';

    public function __construct(
        private readonly Addresses $addresses,
        private readonly AddressBookQueries $reader,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.clear';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('command.clear');
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
                required: false,
                suggestions: SuggestionSource::OnDemand,
            ),
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

        if (!$this->addresses->clear($id, $chapter, trim($input->text(self::FIELD)))) {
            return CommandOutcome::done(Message::error(
                $this->translator->translate(AddressBookSettings::key('message.unknown'), ['entry' => $id]),
            ));
        }

        return CommandOutcome::done(Message::info(
            $this->translator->translate(AddressBookSettings::key('message.cleared'), ['chapter' => $chapter]),
        ));
    }
}
