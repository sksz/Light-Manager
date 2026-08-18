<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Query;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\AddressBookView;
use LightManager\Module\AddressBook\Application\Addresses;

/**
 * `address-book.entries` — cała książka adresowa (krok 60).
 *
 * **Pokolenie jest prawdziwym licznikiem**, nie `VOLATILE`: książka zmienia się
 * w czterech miejscach i wszystkie biją licznik w `Addresses`, więc zachodzi
 * warunek, pod którym D93 nr 1 pozwala pamiętać wynik.
 *
 * **Argument `chapter` jest opcjonalny i zmienia szerokość odpowiedzi, nie jej
 * treść**: bez niego wiersz niesie trzy pola, które ma każdy wpis; z nim —
 * dodatkowo wartości wskazanego rozdziału, spłaszczone jako `<rozdział>.<pole>`.
 * Przedrostek jest obowiązkowy, bo bez niego pole rozdziału o nazwie `name`
 * przykryłoby nazwę wpisu i nikt by tego nie zauważył.
 *
 * **Materiału uwierzytelnienia w wierszach nie ma** — nie dlatego, że go nie
 * pokazujemy, tylko dlatego, że go w książce nie ma (reguła 11w, D105).
 */
final class EntriesQuery implements QueryInterface
{
    public const ARGUMENT = 'chapter';

    public function __construct(
        private readonly Addresses $addresses,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.entries';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AddressBookSettings::ID . '.query.entries';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . AddressBookSettings::ID . '.argument.chapter',
                CommandArgumentKind::Text,
                required: false,
            ),
        ];
    }

    public function generation(): int
    {
        return $this->addresses->revision();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $entries = $this->addresses->entries();
        $chapter = trim($input->text(self::ARGUMENT));
        $view = new AddressBookView(
            $entries,
            $this->addresses->chapters(),
            $this->addresses->location(),
            $this->addresses->problem(),
        );

        return QueryResult::owned(
            AddressBookSettings::ID,
            $view,
            static function () use ($entries, $chapter): array {
                $rows = [];

                foreach ($entries as $entry) {
                    $rows[] = EntryRow::of($entry, $chapter === '' ? [] : [$chapter]);
                }

                return $rows;
            },
        );
    }
}
