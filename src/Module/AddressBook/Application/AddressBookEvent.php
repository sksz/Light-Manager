<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

use LightManager\Application\Event\EventDeclaration;

/**
 * Momenty, o których książka adresowa ogłasza (krok 60, przez mechanizm
 * kroku 46).
 *
 * Cztery, i każde ma odbiorcę od pierwszego dnia: trzy pierwsze mówią ekranom
 * modułów, że spis, który rysują, właśnie się zmienił, a czwarte — że przybyło
 * pól, więc zakładka ma inne kolumny niż przed chwilą.
 *
 * Zdarzenia niosą **wyłącznie tożsamość** (D40 P5): kto chce wiedzieć, co się
 * zmieniło, pyta kwerendą. Zamknięcie słownika jest konstrukcyjne — deklaracje
 * powstają z `cases()` (11o'').
 */
enum AddressBookEvent: string
{
    case EntryAdded = 'address-book.entry.added';

    case EntryChanged = 'address-book.entry.changed';

    case EntryRemoved = 'address-book.entry.removed';

    /** Ktoś zapowiedział użycie rozdziału albo pola, którego wcześniej nie było. */
    case ChapterDeclared = 'address-book.chapter.declared';

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
