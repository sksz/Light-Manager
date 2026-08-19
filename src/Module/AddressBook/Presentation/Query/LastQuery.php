<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;

/**
 * `address-book.last` — identyfikator wpisu dopisanego **ostatnio w tym
 * uruchomieniu** (krok 60, D105 nr 6).
 *
 * Istnieje z jednego, policzonego powodu: **komenda oddaje zdanie, nie daną**.
 * Moduł, który dopisał wpis komendą `address-book.add`, nie ma jak poznać jego
 * identyfikatora — a przy migracji starej książki potrzebuje go do każdego
 * `set`. Pętla jest jednowątkowa, więc w obrębie jednego taktu odpowiedź jest
 * jednoznaczna; poza taktem jest tym, czym się nazywa: ostatnim dopisanym.
 *
 * `VOLATILE`, bo zmienia się dokładnie wtedy, kiedy nikt jej nie pilnuje, a jej
 * policzenie kosztuje odczyt pola.
 */
final class LastQuery implements QueryInterface
{
    public function __construct(private readonly Addresses $addresses)
    {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.last';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('query.last');
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return self::VOLATILE;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $id = $this->addresses->lastAddedId();

        return $id === '' ? QueryResult::empty() : QueryResult::value('id', $id);
    }
}
