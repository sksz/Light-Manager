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
use LightManager\Module\AddressBook\Presentation\AddressBookQueries;
use LightManager\Module\AddressBook\Presentation\EntryFlow;
use LightManager\Module\AddressBook\Presentation\Suggestions;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `address-book.edit <wpis> [rozdział]` — łańcuch okien po polach rozdziału
 * (krok 60).
 *
 * Okno bierze z `EntryFlow` — **tego samego**, którym prowadzi zmianę `F4`
 * w tabeli (11n). Gdyby budowała je sama, byłaby drugą implementacją tej samej
 * czynności, a taka pamiętałaby o zapisie po każdym ogniwie dopóty, dopóki ktoś
 * nie poprawiłby jednej z nich.
 */
final class EditCommand implements CommandInterface, SuggestsArguments, OpensOverlay
{
    public const ENTRY = 'entry';

    public const CHAPTER = 'chapter';

    public function __construct(
        private readonly EntryFlow $flow,
        private readonly AddressBookQueries $reader,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.edit';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('command.edit');
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

    public function overlayFor(CommandInput $input): ?OverlayOutcome
    {
        $id = Suggestions::idOf($input->text(self::ENTRY));

        if ($this->reader->entry($id) === null) {
            return null;
        }

        $overlay = $this->flow->begin($id, trim($input->text(self::CHAPTER)));

        return $overlay === null ? null : OverlayOutcome::replace($overlay);
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $id = Suggestions::idOf($input->text(self::ENTRY));
        $key = $this->reader->entry($id) === null ? 'message.unknown' : 'message.noFields';

        return CommandOutcome::done(Message::warning($this->translator->translate(
            AddressBookSettings::key($key),
            ['entry' => $id, 'chapter' => trim($input->text(self::CHAPTER))],
        )));
    }
}
