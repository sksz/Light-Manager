<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Query;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Query\QueryInterface;
use LightManager\Application\Query\QueryResult;
use LightManager\Module\AddressBook\Application\AddressBookSettings;
use LightManager\Module\AddressBook\Application\Addresses;
use LightManager\Module\AddressBook\Application\ChapterList;

/**
 * `address-book.chapters` — **wszystkie** rozdziały, o których wiadomo
 * (krok 60).
 *
 * Wszystkie znaczy tu: zadeklarowane w tym uruchomieniu **plus** obecne
 * w danych. Rozdział bez deklaracji nie znika ze spisu i nie jest niczyją
 * sierotą — brakuje mu wyłącznie opisu pól, co mówi kolumna `declared`
 * (D104 nr 2).
 *
 * Pokolenie bierze się z licznika deklaracji i licznika wpisów naraz, bo spis
 * zmienia obie drogi: nową deklaracją i wpisem, który dostał wartość
 * w rozdziale dotąd nieużywanym.
 */
final class ChaptersQuery implements QueryInterface
{
    public function __construct(private readonly Addresses $addresses)
    {
    }

    public function name(): string
    {
        return AddressBookSettings::ID . '.chapters';
    }

    public function descriptionKey(): string
    {
        return AddressBookSettings::key('query.chapters');
    }

    public function arguments(): array
    {
        return [];
    }

    public function generation(): int
    {
        return $this->addresses->chapterGeneration();
    }

    public function ask(CommandInput $input): QueryResult
    {
        $list = new ChapterList($this->addresses->chapterViews());

        return QueryResult::owned(AddressBookSettings::ID, $list, static function () use ($list): array {
            $rows = [];

            foreach ($list->chapters as $chapter) {
                $rows[] = [
                    'chapter' => $chapter->id,
                    'title' => $chapter->titleKey,
                    'fields' => count($chapter->fields),
                    'declared' => $chapter->declared,
                ];
            }

            return $rows;
        });
    }
}
