<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Command;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Domain\ValueObject\Message;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Domain\Exception\InvalidAddressEntryException;
use LightManager\Module\AddressBook\Presentation\EntryFlow;
use LightManager\Presentation\Ui\Command\OpensOverlay;
use LightManager\Presentation\Ui\OverlayOutcome;

/**
 * `address-book.add [nazwa] [adres]` — dopisuje wpis (krok 60).
 *
 * **Komenda i `F7` prowadzą jedną czynność, a nie dwie** (11n): bez argumentów
 * otwiera ten sam łańcuch okien, co klawisz, przez zdolność `OpensOverlay`
 * z kroku 47. Z argumentami dopisuje wprost — bo wtedy nie ma o co pytać.
 *
 * Zdanie po dopisaniu **niesie identyfikator**, i to jest w tej komendzie
 * najważniejsze: nazwa bywa pusta i powtarzalna, więc identyfikator jest jedyną
 * rzeczą, którą użytkownik może potem wpisać w `address-book.remove` albo
 * zapisać w cudzym module (D105 nr 4).
 */
final class AddCommand implements CommandInterface, OpensOverlay
{
    public const NAME = 'name';

    public const ADDRESS = 'address';

    public function __construct(
        private readonly Addresses $addresses,
        private readonly EntryFlow $flow,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.add';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AddressBookSettings::ID . '.command.add';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::NAME,
                'module.' . AddressBookSettings::ID . '.argument.name',
                CommandArgumentKind::Text,
                required: false,
            ),
            new CommandArgument(
                self::ADDRESS,
                'module.' . AddressBookSettings::ID . '.argument.address',
                CommandArgumentKind::Text,
                required: false,
            ),
        ];
    }

    /** Bez argumentów pyta oknami; z choćby jednym — wykonuje się zwyczajnie. */
    public function overlayFor(CommandInput $input): ?OverlayOutcome
    {
        if (trim($input->text(self::NAME)) !== '' || trim($input->text(self::ADDRESS)) !== '') {
            return null;
        }

        return OverlayOutcome::replace($this->flow->add());
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        try {
            $entry = $this->addresses->add(trim($input->text(self::NAME)), trim($input->text(self::ADDRESS)));
        } catch (InvalidAddressEntryException $problem) {
            return CommandOutcome::stay(Message::error(
                $this->translator->translate($problem->problemKey(), $problem->problemParameters()),
            ));
        }

        return CommandOutcome::done(Message::info($this->translator->translate(
            'module.' . AddressBookSettings::ID . '.added',
            ['name' => $entry->label(), 'id' => $entry->id],
        )));
    }
}
