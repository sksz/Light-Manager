<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

use LightManager\Application\Event\EventDeclaration;

/**
 * Zdarzenia książki adresowej (krok 60).
 *
 * Trzy, bo trzy są czynności zmieniające spis. Odbiorcą jest każdy, kto trzyma
 * u siebie identyfikator wpisu — moduł sesji zdalnej rysujący spis i wpis
 * tunelowy modułu Dockera wskazujący adres, który właśnie zniknął. Bez nich
 * jedyną drogą byłoby odpytywanie książki co klatkę.
 *
 * Słownik jest zamknięty konstrukcyjnie (11o''): nazwy pochodzą z `cases()`,
 * więc publikacja i spis u odbiorcy nie mają jak się rozjechać.
 */
enum AddressBookEvent: string
{
    case EntryAdded = 'address-book.entry.added';

    case EntryChanged = 'address-book.entry.changed';

    case EntryRemoved = 'address-book.entry.removed';

    public function labelKey(): string
    {
        return 'module.' . AddressBookSettings::ID . '.event.'
            . substr($this->value, strlen(AddressBookSettings::ID) + 1);
    }

    /** @return list<EventDeclaration> */
    public static function declarations(): array
    {
        return array_map(
            static fn (self $event): EventDeclaration => new EventDeclaration($event->value, $event->labelKey()),
            self::cases(),
        );
    }
}
