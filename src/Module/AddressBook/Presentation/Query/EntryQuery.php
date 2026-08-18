<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Query;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandArgumentKind;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;

/**
 * `address-book.entry` — **jeden** wpis wskazany identyfikatorem (krok 60).
 *
 * Powstała z policzonej potrzeby, a nie z symetrii: wpis tunelowy modułu
 * Dockera szukał do tego kroku swojego adresu, przeglądając **wszystkie**
 * wiersze książki hostów, bo innej drogi nie było.
 *
 * Wiersz niesie **wszystkie rozdziały wpisu** spłaszczone (`<rozdział>.<pole>`),
 * bo pytający o jeden wpis zwykle chce go w całości, a wpisów jest tu z definicji
 * jeden. Odpowiedź pusta znaczy „nie ma takiego" i jest **zwykłym stanem**:
 * pytający musi umieć bez niej żyć (reguła 15g).
 *
 * Dla zgodności wstecz argument przyjmuje także **jednoznaczną nazwę** — wpisy
 * zapisane u obcych przed tym krokiem trzymają nazwę, bo wtedy to ona była
 * tożsamością (krok 48).
 */
final class EntryQuery implements QueryInterface
{
    public const ARGUMENT = 'id';

    public function __construct(
        private readonly Addresses $addresses,
    ) {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.entry';
    }

    public function descriptionKey(): string
    {
        return 'module.' . AddressBookSettings::ID . '.query.entry';
    }

    public function arguments(): array
    {
        return [
            new CommandArgument(
                self::ARGUMENT,
                'module.' . AddressBookSettings::ID . '.argument.id',
                CommandArgumentKind::Text,
                required: true,
                suggestions: SuggestionSource::OnDemand,
            ),
        ];
    }

    public function generation(): int
    {
        return $this->addresses->revision();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $entry = $this->addresses->resolve(trim($input->text(self::ARGUMENT)));

        if ($entry === null) {
            return QueryResult::of([]);
        }

        return QueryResult::owned(
            AddressBookSettings::ID,
            $entry,
            static fn (): array => [EntryRow::of($entry, EntryRow::chaptersOf($entry))],
        );
    }
}
