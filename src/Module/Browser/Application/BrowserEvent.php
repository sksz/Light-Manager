<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Application;

use LightManager\Application\Event\EventDeclaration;

/**
 * Co przeglądarka ogłasza reszcie aplikacji — zamknięty zbiór siedemnastu
 * zdarzeń (krok 46, D83).
 *
 * Moduł publikuje **z nazwy**, a nie tonem komunikatu, i to jest cały powód, dla
 * którego ta klasa istnieje. Wszystkie zdania przeglądarki schodzą się
 * w `LoopState::report()`, więc trzy zdarzenia rdzenia odróżniają powodzenie od
 * awarii — ale nie odróżniają **kopiowania od usunięcia**, a odbiorca chcący
 * zagrać co innego przy jednym, a co innego przy drugim, potrzebuje właśnie tego.
 *
 * Zbiór dzieli się na dwie części o różnej naturze. **Trzy zdarzenia ruchu**
 * (kursor, wejście do katalogu, zaznaczenie) padają często i o nic nie proszą —
 * odbiorca sam pilnuje, żeby nie odpowiadać na każde z osobna. **Czternaście
 * zdarzeń czynności** to siedem operacji zmieniających dysk, każda w dwóch
 * postaciach: udanej i nieudanej. Rozbicie po operacjach jest rozstrzygnięciem
 * użytkownika, świadomie kupionym za długość spisu w oknie odbiorcy.
 *
 * Enum, a nie napisy w miejscach wywołania — deklaracja powstaje z `cases()`,
 * więc spis pokazywany użytkownikowi nie może zawierać wiersza, którego nikt nie
 * publikuje, ani przemilczeć zdarzenia, które pada. Nazwy stoją w przestrzeni
 * identyfikatora modułu, jak nazwy komend i klucze napisów; nazwę spoza niej
 * odsiałby `EventRegistry`.
 */
enum BrowserEvent: string
{
    /** Kursor przeszedł na inny wpis — w liście albo w drzewie. */
    case CursorMoved = 'browser.cursor.moved';

    /** Panel wszedł do innego katalogu — w głąb albo wyżej. */
    case DirectoryEntered = 'browser.directory.entered';

    /** Wpis został zaznaczony albo odznaczony. */
    case EntryMarked = 'browser.entry.marked';

    case RenameDone = 'browser.rename.done';

    case RenameFailed = 'browser.rename.failed';

    case MakeDirectoryDone = 'browser.mkdir.done';

    case MakeDirectoryFailed = 'browser.mkdir.failed';

    case CopyDone = 'browser.copy.done';

    case CopyFailed = 'browser.copy.failed';

    case MoveDone = 'browser.move.done';

    case MoveFailed = 'browser.move.failed';

    case TrashDone = 'browser.trash.done';

    case TrashFailed = 'browser.trash.failed';

    /** Usunięcie **trwałe** — droga `Shift`+`F8` i odpowiedź „usuń trwale". */
    case DeleteDone = 'browser.delete.done';

    case DeleteFailed = 'browser.delete.failed';

    case UndoDone = 'browser.undo.done';

    case UndoFailed = 'browser.undo.failed';

    /**
     * Klucz napisu z nazwą zdarzenia: `module.browser.event.copy.done`.
     *
     * Środek nazwy jest zarazem środkiem klucza, więc dołożenie zdarzenia nie
     * wymaga wymyślania drugiej nazwy dla tej samej rzeczy.
     */
    public function labelKey(): string
    {
        return 'module.' . BrowserSettings::ID . '.event.'
            . substr($this->value, strlen(BrowserSettings::ID) + 1);
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
