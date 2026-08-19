<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Query;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Application\ChapterField;

/**
 * `address-book.value` — jedna wartość pola, **także maskowanego** (krok 60).
 *
 * Kwerenda istnieje po to, żeby wartość pola rodzaju `secret` dała się w ogóle
 * przeczytać: w wierszach spisu stoi w jej miejscu `set`/`unset`, bo ścieżka
 * klucza prywatnego nie ma się wyświetlać w każdej tabeli. **Przegrodą to nie
 * jest i plan mówi to wprost**: rejestr kwerend nie zna wołającego (reguła
 * kwerendy nr 2), a dostęp do rozdziałów jest jednakowy (D104 nr 1) — więc
 * odpowie każdemu, kto zapyta.
 *
 * `VOLATILE`, bo odpowiedzi z materiałem uwierzytelnienia **nie chcemy
 * w pamięci rejestru**: pamiętana leżałaby tam do najbliższej zmiany książki,
 * czyli zwykle do końca uruchomienia.
 */
final class ValueQuery implements QueryInterface
{
    public const ENTRY = 'entry';

    public const CHAPTER = 'chapter';

    public const FIELD = 'field';

    public function __construct(private readonly Addresses $addresses)
    {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.value';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('query.value');
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
        ];
    }

    public function generation(): int
    {
        return self::VOLATILE;
    }

    public function ask(CommandInput $input): QueryResult
    {
        $entry = $this->addresses->find(trim($input->text(self::ENTRY)));
        $value = $entry?->value(trim($input->text(self::CHAPTER)), trim($input->text(self::FIELD)));

        return $value === null
            ? QueryResult::empty()
            : QueryResult::value('value', ChapterField::asText($value));
    }
}
