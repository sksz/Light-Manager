<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Presentation\AddressBookQueries;
use LightManager\Module\AddressBook\Presentation\AddressBookScreen;
use LightManager\Module\AddressBook\Presentation\Suggestions;

/**
 * `address-book.show [rozdział]` — otwiera książkę, opcjonalnie na wskazanej
 * zakładce (krok 60).
 *
 * Druga droga do tego, co `Ctrl`+`W`, z tego samego powodu, co `file-info.show`.
 * Argument ma odbiorcę od pierwszego dnia: ekrany modułów zamawiają nią
 * **własną zakładkę** (`address-book.show ssh`), skoro dopisywanie i zmiana
 * wpisów zeszły z nich do książki.
 */
final class ShowCommand implements CommandInterface, SuggestsArguments
{
    public const CHAPTER = 'chapter';

    public function __construct(
        private readonly AddressBookScreen $screen,
        private readonly AddressBookQueries $reader,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.show';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('command.show');
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::CHAPTER,
                AddressBookSettings::key('argument.chapter'),
                required: false,
                suggestions: SuggestionSource::OnDemand,
            ),
        ];
    }

    public function suggestions(string $argument, string $prefix): array
    {
        return $argument === self::CHAPTER ? Suggestions::chapters($this->reader, $prefix) : [];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $chapter = trim($input->text(self::CHAPTER));

        if ($chapter !== '') {
            $this->screen->useChapter($chapter);
        }

        return CommandOutcome::opens(AddressBookScreen::ID);
    }
}
