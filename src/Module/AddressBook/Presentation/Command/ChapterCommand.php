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

/**
 * `address-book.chapter` — moduł zakłada swój rozdział książki (krok 60,
 * D105 nr 3).
 *
 * **To jest cała droga, którą pola trafiają do wpisu**, i jest to droga
 * z reguły 15g: zakładający podaje **trzy napisy** — swój identyfikator, nazwę
 * własnej kwerendy deklarującej pola i klucz napisu z tytułem rozdziału — a nie
 * ani jednego typu. Rdzeń nie bierze w tym udziału i nie wie o rozdziałach nic
 * (D104 nr 2 zostaje w mocy).
 *
 * Komenda jest **idempotentna**: moduł nie musi pamiętać, czy już prosił, a przy
 * leniwym składaniu modułów nie miałby jak. Wołanie drugi raz zastępuje wpis
 * rozdziału tym samym.
 *
 * Stoi w rejestrze komend jak każda inna, więc widzi ją także użytkownik w oknie
 * komend — i to jest w porządku: rozdział założony ręcznie znika przy następnym
 * uruchomieniu, bo rozdziały nie są zapisywane (opis w `AddressChapter`).
 */
final class ChapterCommand implements CommandInterface
{
    public const OWNER = 'module';

    public const QUERY = 'query';

    public const LABEL = 'label';

    public function __construct(
        private readonly Addresses $addresses,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.chapter';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AddressBookSettings::ID . '.command.chapter';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::OWNER,
                'module.' . AddressBookSettings::ID . '.argument.module',
                CommandArgumentKind::Text,
            ),
            new CommandArgument(
                self::QUERY,
                'module.' . AddressBookSettings::ID . '.argument.query',
                CommandArgumentKind::Text,
            ),
            new CommandArgument(
                self::LABEL,
                'module.' . AddressBookSettings::ID . '.argument.label',
                CommandArgumentKind::Text,
                required: false,
            ),
        ];
    }

    public function execute(CommandInput $input): CommandOutcome
    {
        $owner = trim($input->text(self::OWNER));
        $query = trim($input->text(self::QUERY));

        if ($owner === '' || $query === '') {
            return CommandOutcome::stay(Message::warning(
                $this->translator->translate('module.' . AddressBookSettings::ID . '.chapter.incomplete'),
            ));
        }

        $label = trim($input->text(self::LABEL));
        $this->addresses->declareChapter($owner, $query, $label === '' ? 'module.' . $owner . '.name' : $label);

        return CommandOutcome::done();
    }
}
