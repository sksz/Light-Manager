<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

/**
 * Migawka jednego rozdziału — ładunek kwerendy `address-book.fields` i element
 * `ChapterList` (krok 60).
 *
 * `declared` mówi, czy **ktokolwiek** zapowiedział w tym uruchomieniu użycie
 * tego rozdziału. Fałsz nie znaczy „zakazany" ani „osierocony": znaczy, że
 * książka nie ma opisu pól, więc pokaże surowe klucze — a czytać i zmieniać
 * wolno tak samo, jak wszędzie indziej (D104 nr 2).
 */
final readonly class ChapterView
{
    /** @param list<ChapterField> $fields */
    public function __construct(
        public string $id,
        public string $titleKey,
        public bool $declared,
        public array $fields = [],
    ) {
    }

    public function field(string $key): ?ChapterField
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(static fn (ChapterField $field): string => $field->key, $this->fields);
    }
}
