<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Presentation\Query;

use LightManager\Module\AddressBook\Application\AddressBookView;
use LightManager\Module\AddressBook\Application\ChapterField;
use LightManager\Module\AddressBook\Application\ChapterView;
use LightManager\Module\AddressBook\Domain\ValueObject\AddressEntry;

/**
 * Zamiana wpisu na wiersz kwerendy — **jeden rachunek dla obu kwerend wpisów**
 * (krok 60).
 *
 * Stoi osobno, bo `address-book.entries` i `address-book.entry` różnią się
 * wyłącznie liczbą wpisów, a dwie kopie tej zamiany rozjechałyby się przy
 * pierwszym nowym rodzaju pola — i rozjazd byłby niewidoczny, bo obcy widzi
 * tylko jedną z nich naraz.
 *
 * Trzy reguły wiersza: **zawsze niesie `id` i `name`**; z podanym rozdziałem
 * niesie **wszystkie jego pola** (także te, których wpis nie ma — wtedy pusto,
 * bo brak kolumny i brak wartości to dla obcego dwie różne rzeczy); wartość
 * pola maskowanego zastępuje **`set`/`unset`**.
 *
 * Rozdział bez deklaracji oddaje **surowe klucze wpisu** — wartości są, opisu
 * nie ma, a to nie jest powód, żeby ich nie pokazać (D104 nr 2).
 */
final class EntryRow
{
    public const MASKED_SET = 'set';

    public const MASKED_UNSET = 'unset';

    /**
     * @return list<array<string, string|int|bool>>
     */
    public static function listOf(AddressBookView $view, string $chapter, ?ChapterView $fields): array
    {
        $rows = [];

        foreach ($view->entries as $entry) {
            $rows[] = self::of($entry, $chapter, $fields);
        }

        return $rows;
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function of(AddressEntry $entry, string $chapter, ?ChapterView $fields): array
    {
        $row = ['id' => $entry->id, 'name' => $entry->name];

        if ($chapter === '') {
            $row['chapters'] = implode(',', $entry->chapters());

            return $row;
        }

        if ($fields === null || $fields->fields === []) {
            foreach ($entry->valuesOf($chapter) as $key => $value) {
                $row[$key] = ChapterField::asText($value);
            }

            return $row;
        }

        foreach ($fields->fields as $field) {
            $row[$field->key] = self::valueOf($field, $entry->value($chapter, $field->key));
        }

        return $row;
    }

    private static function valueOf(ChapterField $field, string|int|bool|null $value): string|int|bool
    {
        if (!$field->kind->isMasked()) {
            return $value ?? '';
        }

        return ChapterField::asText($value) === '' ? self::MASKED_UNSET : self::MASKED_SET;
    }
}
