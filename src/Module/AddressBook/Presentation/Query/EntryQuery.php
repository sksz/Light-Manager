<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Query;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\SuggestionSource;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\AddressBookView;
use LightManager\Module\AddressBook\Application\Addresses;

/**
 * `address-book.entry` — **jeden** wpis, opcjonalnie z wartościami rozdziału
 * (krok 60).
 *
 * Powstaje z policzonej potrzeby, a nie dla symetrii: cel tunelu Dockera
 * potrzebuje jednego wpisu wskazanego identyfikatorem, a bez tej kwerendy
 * musiałby przejść po całej książce, żeby go znaleźć — dokładnie tak, jak robił
 * to do tego kroku z wierszami `ssh.hosts`.
 *
 * Wpisu nieznanego **nie ma i tyle**: pusta lista wierszy, bez powodu — bo
 * „nie ma takiego wpisu" jest odpowiedzią, a nie awarią.
 */
final class EntryQuery implements QueryInterface
{
    public const ENTRY = 'entry';

    public const CHAPTER = 'chapter';

    public function __construct(private readonly Addresses $addresses)
    {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.entry';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('query.entry');
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
                required: false,
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
        $entry = $this->addresses->find(trim($input->text(self::ENTRY)));
        $chapter = trim($input->text(self::CHAPTER));

        // Wpis pytany **o rozdział, do którego nie należy**, jest pustą
        // odpowiedzią, a nie wierszem z pustymi kolumnami — tak samo, jak
        // w spisie (`address-book.entries`).
        if ($entry !== null && $chapter !== '' && !$entry->hasChapter($chapter)) {
            $entry = null;
        }

        $view = new AddressBookView(
            $entry === null ? [] : [$entry],
            $this->addresses->location(),
            $this->addresses->problemKey(),
        );
        $fields = $chapter === '' ? null : $this->addresses->chapterView($chapter);

        return QueryResult::owned(
            AddressBookSettings::ID,
            $view,
            static fn (): array => EntryRow::listOf($view, $chapter, $fields),
        );
    }
}
