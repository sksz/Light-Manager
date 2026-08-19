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
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;

/**
 * `address-book.entries` — wszystkie wpisy, opcjonalnie z wartościami jednego
 * rozdziału (krok 60).
 *
 * **Argument `chapter` przyjmuje dowolny rozdział** i to jest sedno zasady tego
 * kroku: moduł Dockera pyta o rozdział `ssh` tą samą kwerendą, którą moduł Ssh
 * pyta o swój, a książka nie wie i nie pyta, kto pyta (D104 nr 1).
 *
 * Wartość pola maskowanego **nie wchodzi do wierszy** — jej miejsce zajmuje
 * `set` albo `unset`. Nie jest to przegroda, tylko obrona przed hałasem:
 * ścieżka klucza prywatnego nie ma się wyświetlać w każdym spisie. Kto jej
 * potrzebuje, pyta `address-book.value` i **dostaje ją** (D104 nr 6).
 */
final class EntriesQuery implements QueryInterface
{
    public const CHAPTER = 'chapter';

    public function __construct(private readonly Addresses $addresses)
    {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.entries';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('query.entries');
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

    public function generation(): int
    {
        return $this->addresses->revision();
    }

    /**
     * Wpisy **należące do rozdziału** — czyli mające w nim jakąkolwiek wartość.
     *
     * Bez argumentu odpowiedzią jest cała książka; z argumentem — jej wycinek,
     * i to jest treść pytania „co jest w tym rozdziale". Odpowiedź nieodsiana
     * wyglądała jak spis wszystkiego z pustymi kolumnami: klaster stawał
     * w zakładce Dockera, choć nie ma z nią nic wspólnego.
     *
     * Wpis **świeżo dopisany nie należy jeszcze nigdzie** i to jest zamierzone:
     * należy się przez wartości, a nie przez to, że go widziano. Dlatego
     * dopisanie wpisu z zakładki rozdziału prowadzi od razu przez jego pola.
     *
     * @param list<AddressEntry> $entries
     *
     * @return list<AddressEntry>
     */
    private static function of(array $entries, string $chapter): array
    {
        if ($chapter === '') {
            return $entries;
        }

        return array_values(array_filter(
            $entries,
            static fn (AddressEntry $entry): bool => $entry->hasChapter($chapter),
        ));
    }

    public function ask(CommandInput $input): QueryResult
    {
        $chapter = trim($input->text(self::CHAPTER));
        $view = new AddressBookView(
            self::of($this->addresses->all(), $chapter),
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
