<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

/**
 * Rozdział książki — pola, które jeden moduł dokłada do każdego wpisu
 * (krok 60, D105 nr 3).
 *
 * Rozdział **zakłada się komendą** (`address-book.chapter`), a jego treść —
 * spis pól — czyta się z **kwerendy wskazanej przez zakładającego**. Stąd dwa
 * napisy zamiast jednego: `owner` mówi, czyje są wartości (i pod tym kluczem
 * stoją we wpisie), a `query` — skąd wziąć ich deklarację. Rdzeń nie bierze
 * w tym udziału i nie ma o rozdziałach pojęcia.
 *
 * **Rozdziały nie są zapisywane na dysk** i to jest decyzja, nie przeoczenie:
 * zakłada się je przy każdym uruchomieniu, więc moduł wyłączony albo odrzucony
 * po prostu nie ma rozdziału, a jego **wartości we wpisach zostają nietknięte**
 * (leżą w sekcji książki i przeżywają nieobecność właściciela). Spis rozdziałów
 * zapisany na dysku musiałby być sprzątany, a nie ma komu.
 */
final readonly class AddressChapter
{
    /** @param list<ChapterField> $fields */
    public function __construct(
        /** Identyfikator modułu — klucz wartości we wpisie. */
        public string $owner,
        /** Nazwa kwerendy, z której książka czyta deklarację pól. */
        public string $query,
        /** Klucz katalogu z tytułem rozdziału; zwykle `module.<owner>.name`. */
        public string $labelKey,
        public array $fields = [],
    ) {
    }

    /** @param list<ChapterField> $fields */
    public function withFields(array $fields): self
    {
        return new self($this->owner, $this->query, $this->labelKey, $fields);
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

    public function isEmpty(): bool
    {
        return $this->fields === [];
    }
}
