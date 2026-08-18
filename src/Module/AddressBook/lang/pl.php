<?php

declare(strict_types=1);

/*
 * Napisy modułu „Książka adresowa” — polski.
 *
 * **Każdy klucz musi zaczynać się od `module.address-book.`** — katalog
 * przyjmuje wyłącznie takie i pomija resztę.
 *
 * Etykiety pól rozdziałów **nie mają tu kluczy i mieć nie będą**: deklaruje je
 * moduł, który rozdział założył, a książka je wyłącznie tłumaczy, nie wiedząc,
 * czyje są (krok 60, D105 nr 3).
 */

return [
    'module.address-book.name' => 'Książka adresowa',
    'module.address-book.description' => 'Adresy, pod którymi coś stoi — wspólne dla wszystkich modułów, które się pod nie łączą.',
    'module.address-book.screen' => 'Książka adresowa',

    // Ustawienia.
    'module.address-book.setting.order' => 'Kolejność spisu',

    // Kolumny spisu.
    'module.address-book.column.name' => 'Nazwa',
    'module.address-book.column.address' => 'Adres',
    'module.address-book.column.id' => 'Wpis',

    // Stany spisu.
    'module.address-book.empty' => 'Książka jest pusta. Nowy adres dopisuje F7.',
    'module.address-book.empty.filtered' => 'Żaden wpis nie pasuje do zawężenia. Esc je zdejmuje.',
    'module.address-book.unnamed' => '(bez nazwy)',
    'module.address-book.noAddress' => '(bez adresu)',
    'module.address-book.filter.active' => 'zawężone: {filter}',
    'module.address-book.book.unreadable' => 'Nie udało się przeczytać książki adresowej — plik stanu jest uszkodzony albo zapisany inną wersją.',

    // Klawisze ekranu.
    'module.address-book.key.add' => 'dopisz adres',
    'module.address-book.key.add.short' => 'dopisz',
    'module.address-book.key.edit' => 'zmień wpis',
    'module.address-book.key.edit.short' => 'zmień',
    'module.address-book.key.remove' => 'usuń wpis',
    'module.address-book.key.remove.short' => 'usuń',
    'module.address-book.key.filter' => 'zawęź spis',
    'module.address-book.key.filter.short' => 'zawęź',

    // Łańcuch okien wpisu.
    'module.address-book.prompt.name' => 'Nazwa wpisu — wolno zostawić pustą.',
    'module.address-book.prompt.name.field' => 'Nazwa',
    'module.address-book.prompt.address' => 'Adres wpisu „{name}” — wolno zostawić pusty.',
    'module.address-book.prompt.address.field' => 'Adres',
    'module.address-book.prompt.field' => '{field} — wpis „{name}”.',
    'module.address-book.prompt.keep' => 'zostaw bez zmiany',
    'module.address-book.prompt.filter' => 'Zawężenie spisu — po nazwie, adresie i identyfikatorze.',
    'module.address-book.prompt.filter.field' => 'Zawężenie',
    'module.address-book.field.flag.yes' => 'tak',
    'module.address-book.field.flag.no' => 'nie',

    // Zdania o skutkach.
    'module.address-book.confirm.remove' => 'Usunąć wpis „{name}” z książki adresowej?',
    'module.address-book.added' => 'Dopisano „{name}” — identyfikator wpisu: {id}.',
    'module.address-book.changed' => 'Zmieniono wpis „{name}” ({id}).',
    'module.address-book.removed' => 'Usunięto wpis „{name}”.',
    'module.address-book.copied' => 'Skopiowano adres: {address}',
    'module.address-book.unknown' => 'W książce nie ma wpisu „{entry}” — sprawdź identyfikator.',
    'module.address-book.chapter.incomplete' => 'Rozdział wymaga identyfikatora modułu i nazwy kwerendy z deklaracją pól.',

    // Powody odrzucenia wpisu.
    'module.address-book.entry.id.invalid' => 'Identyfikator wpisu „{id}” nie jest ośmioznakowym napisem szesnastkowym.',
    'module.address-book.entry.name.invalid' => 'Nazwa „{name}” zawiera znaki sterujące albo jest za długa.',
    'module.address-book.entry.address.invalid' => 'Adres „{address}” zawiera odstęp, znak sterujący albo zaczyna się od myślnika.',
    'module.address-book.entry.chapter.invalid' => 'Rozdział „{chapter}” nie jest identyfikatorem modułu.',
    'module.address-book.entry.field.invalid' => 'Pole „{field}” nie jest poprawnym kluczem pola.',

    // Komendy.
    'module.address-book.command.show' => 'pokaż książkę adresową',
    'module.address-book.command.add' => 'dopisz adres do książki',
    'module.address-book.command.remove' => 'usuń wpis z książki',
    'module.address-book.command.chapter' => 'załóż rozdział książki dla modułu',

    // Kwerendy.
    'module.address-book.query.entries' => 'wpisy książki adresowej',
    'module.address-book.query.entry' => 'jeden wpis książki adresowej',

    // Argumenty komend i kwerend.
    'module.address-book.argument.name' => 'nazwa',
    'module.address-book.argument.address' => 'adres',
    'module.address-book.argument.entry' => 'wpis',
    'module.address-book.argument.id' => 'identyfikator wpisu',
    'module.address-book.argument.chapter' => 'rozdział',
    'module.address-book.argument.module' => 'moduł',
    'module.address-book.argument.query' => 'kwerenda z deklaracją pól',
    'module.address-book.argument.label' => 'klucz napisu z tytułem rozdziału',

    // Zdarzenia.
    'module.address-book.event.entry.added' => 'dopisano adres',
    'module.address-book.event.entry.changed' => 'zmieniono adres',
    'module.address-book.event.entry.removed' => 'usunięto adres',

    // Pomoc.
    'module.address-book.help.start' => 'Ctrl+W otwiera książkę adresową: spis miejsc, pod które łączą się inne moduły.',
    'module.address-book.help.id' => 'Tożsamością wpisu jest jego identyfikator, a nie nazwa — nazwę wolno zmienić i wolno zostawić pustą, a odniesienia innych modułów tego nie zauważą.',
    'module.address-book.help.chapters' => 'Pola poza nazwą i adresem dokładają moduły: sesja zdalna dopisuje port, użytkownika i sposób uwierzytelnienia, a książka pyta o nie w tym samym łańcuchu okien.',
    'module.address-book.help.secrets' => 'Haseł, kluczy ani certyfikatów w książce nie ma i nie będzie — trzyma je ten moduł, który się nimi przedstawia.',
    'module.address-book.help.remove' => 'Usunięcie wpisu nie ostrzega cudzych modułów: wpis tunelowy Dockera wskazujący usunięty adres powie o tym dopiero przy próbie połączenia.',
];
