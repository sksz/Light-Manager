<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\AddressBookView;
use LightManager\Module\AddressBook\Application\ChapterList;
use LightManager\Module\AddressBook\Application\ChapterView;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;
use LightManager\Module\AddressBook\Presentation\Query\EntriesQuery;
use LightManager\Module\AddressBook\Presentation\Query\EntryQuery;
use LightManager\Module\AddressBook\Presentation\Query\FieldsQuery;
use LightManager\Module\AddressBook\Presentation\Query\ValueQuery;

/**
 * Odczyt książki — **przez rejestr kwerend, jak każdy inny** (reguła 11w).
 *
 * Siódma fasada modułowa w projekcie i pierwsza, której właściciel **nie ma
 * żadnej innej drogi do własnych danych**: ekran książki i jej łańcuch okien
 * nie trzymają referencji do modelu w ogóle (to była czwarta wada książki
 * usuniętej w kroku poprzednim). Model widzą wyłącznie komendy i kwerendy.
 *
 * Fasada **nie zna pojęcia cudzego rozdziału** i to jest zamierzone: `chapter`
 * jest tu zwykłym argumentem, a wywołanie z rozdziałem `ssh` wygląda tak samo
 * z modułu Ssh, z modułu Dockera i z ekranu samej książki (D104 nr 1).
 */
final readonly class AddressBookQueries
{
    public function __construct(private QueryRegistry $queries)
    {
    }

    /**
     * Wszystkie wpisy; z podanym rozdziałem ładunek jest ten sam, a różnią się
     * **wiersze** — i to dla obcych, nie dla nas.
     */
    public function book(): AddressBookView
    {
        $payload = $this->ask('entries');

        return $payload instanceof AddressBookView ? $payload : new AddressBookView([], '');
    }

    /** Jeden wpis albo `null`, gdy takiego nie ma. */
    public function entry(string $id): ?AddressEntry
    {
        $payload = $this->ask('entry', [EntryQuery::ENTRY => $id]);

        return $payload instanceof AddressBookView ? $payload->at(0) : null;
    }

    public function chapters(): ChapterList
    {
        $payload = $this->ask('chapters');

        return $payload instanceof ChapterList ? $payload : new ChapterList();
    }

    public function fields(string $chapter): ChapterView
    {
        $payload = $this->ask('fields', [FieldsQuery::CHAPTER => $chapter]);

        return $payload instanceof ChapterView ? $payload : new ChapterView($chapter, '', false);
    }

    /**
     * Wartość pola — **także maskowanego**, bo to jedyna droga, którą wartość
     * pola rodzaju `secret` w ogóle wychodzi z książki (D104 nr 6).
     */
    public function value(string $entry, string $chapter, string $field): string
    {
        $rows = $this->queries->ask(
            AddressBookSettings::ID . '.value',
            new CommandInput([
                ValueQuery::ENTRY => $entry,
                ValueQuery::CHAPTER => $chapter,
                ValueQuery::FIELD => $field,
            ]),
        )->rows();

        $value = $rows[0]['value'] ?? '';

        return is_string($value) ? $value : '';
    }

    /**
     * Wiersze spisu w postaci, w jakiej widzi je obcy — **z zasłoniętymi
     * polami maskowanymi**.
     *
     * Ekran książki rysuje właśnie z nich, a nie z ładunku, i to nie jest
     * ceremonia: dzięki temu tabela pokazuje dokładnie to, co pokaże okno
     * kwerend, a zasłona jest jedna dla obu.
     *
     * @return list<array<string, string|int|bool>>
     */
    public function rows(string $chapter): array
    {
        return $this->queries->ask(
            AddressBookSettings::ID . '.entries',
            new CommandInput([EntriesQuery::CHAPTER => $chapter]),
        )->rows();
    }

    /** @param array<string, string> $arguments */
    private function ask(string $name, array $arguments = []): ?object
    {
        return $this->queries
            ->ask(AddressBookSettings::ID . '.' . $name, new CommandInput($arguments))
            ->payloadFor(AddressBookSettings::ID);
    }
}
