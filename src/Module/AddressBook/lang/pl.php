<?php

declare(strict_types=1);

/*
 * Napisy modułu „Książka adresowa” — polski.
 *
 * **Każdy klucz musi zaczynać się od `module.address-book.`** — katalog
 * przyjmuje wyłącznie takie i pomija resztę.
 *
 * Etykiet **cudzych pól tu nie ma i nie będzie**: rozdział deklaruje ten, kto
 * go używa, a klucz etykiety wskazuje jego katalog (reguła 15).
 */

return [
    'module.address-book.name' => 'Książka adresowa',
    'module.address-book.description' => 'Wspólny spis miejsc: wpis ma nazwę i identyfikator, a pola przy nim dokładają moduły rozdziałami.',
    'module.address-book.screen' => 'Książka adresowa',

    // Zakładki i kolumny.
    'module.address-book.tab.all' => 'Wszystkie',
    'module.address-book.column.name' => 'Nazwa',
    'module.address-book.column.chapters' => 'Rozdziały',
    'module.address-book.column.id' => 'Identyfikator',

    // Rozdział własny książki.
    'module.address-book.chapter.general' => 'Ogólne',
    'module.address-book.field.address' => 'Adres',
    'module.address-book.field.note' => 'Opis',

    // Klawisze ekranu.
    'module.address-book.key.move' => 'przejdź po wpisach',
    'module.address-book.key.chapter' => 'zmień rozdział',
    'module.address-book.key.chapter.short' => 'rozdział',
    'module.address-book.key.edit' => 'zmień pola wpisu',
    'module.address-book.key.edit.short' => 'zmień',
    'module.address-book.key.sort' => 'zmień kolumnę porządkującą',
    'module.address-book.key.sort.short' => 'sortuj',
    'module.address-book.key.add' => 'dopisz wpis',
    'module.address-book.key.add.short' => 'dopisz',
    'module.address-book.key.remove' => 'usuń wpis',
    'module.address-book.key.remove.short' => 'usuń',
    'module.address-book.key.filter' => 'zawęź spis',
    'module.address-book.key.filter.short' => 'szukaj',
    'module.address-book.focus.entries' => 'Wpisy',

    // Górny pas i spis pusty.
    'module.address-book.header.filter' => 'Zawężone do „{filter}” — wpisów: {count}',
    'module.address-book.header.undeclared' => 'Rozdziału „{chapter}” nikt dziś nie używa — wartości są, opisu pól nie ma',
    'module.address-book.empty' => 'Książka jest pusta. F7 dopisuje wpis.',
    'module.address-book.empty.filter' => 'Nic nie pasuje do zawężenia. Ctrl+F zmienia je, Esc na pustym polu je zdejmuje.',

    // Okna.
    'module.address-book.prompt.name' => 'Nazwa wpisu',
    'module.address-book.prompt.name.field' => 'nazwa',
    'module.address-book.prompt.filter' => 'Zawęź spis',
    'module.address-book.prompt.filter.field' => 'szukaj',
    'module.address-book.prompt.field' => '{entry}: {field}',
    'module.address-book.flag.yes' => 'tak',
    'module.address-book.flag.no' => 'nie',
    'module.address-book.confirm.remove' => 'Usunąć wpis „{entry}” wraz z wartościami wszystkich rozdziałów?',
    'module.address-book.confirm.forget' => 'Usunąć wartości rozdziału „{chapter}” ze wszystkich wpisów?',

    // Komendy.
    'module.address-book.command.show' => 'otwórz książkę adresową',
    'module.address-book.command.chapter' => 'zapowiedz użycie rozdziału',
    'module.address-book.command.field' => 'zapowiedz użycie pola rozdziału',
    'module.address-book.command.add' => 'dopisz wpis',
    'module.address-book.command.rename' => 'zmień nazwę wpisu',
    'module.address-book.command.remove' => 'usuń wpis',
    'module.address-book.command.set' => 'zapisz wartość pola',
    'module.address-book.command.clear' => 'wyczyść pole albo rozdział wpisu',
    'module.address-book.command.edit' => 'przejdź po polach rozdziału',
    'module.address-book.command.forget' => 'usuń wartości rozdziału ze wszystkich wpisów',

    // Argumenty komend i kwerend.
    'module.address-book.argument.entry' => 'wpis',
    'module.address-book.argument.chapter' => 'rozdział',
    'module.address-book.argument.field' => 'pole',
    'module.address-book.argument.value' => 'wartość',
    'module.address-book.argument.name' => 'nazwa',
    'module.address-book.argument.title' => 'klucz tytułu',
    'module.address-book.argument.label' => 'klucz etykiety',
    'module.address-book.argument.kind' => 'rodzaj',
    'module.address-book.argument.default' => 'wartość domyślna',
    'module.address-book.argument.choices' => 'dopuszczalne wartości',

    // Kwerendy.
    'module.address-book.query.entries' => 'wpisy książki, opcjonalnie z wartościami rozdziału',
    'module.address-book.query.entry' => 'jeden wpis książki',
    'module.address-book.query.chapters' => 'rozdziały: zadeklarowane i obecne w danych',
    'module.address-book.query.fields' => 'pola rozdziału',
    'module.address-book.query.value' => 'wartość jednego pola, także maskowanego',
    'module.address-book.query.last' => 'identyfikator wpisu dopisanego ostatnio',

    // Rodzaje pól.
    'module.address-book.kind.text' => 'napis',
    'module.address-book.kind.number' => 'liczba',
    'module.address-book.kind.flag' => 'tak/nie',
    'module.address-book.kind.choice' => 'wybór',
    'module.address-book.kind.secret' => 'sekret',
    'module.address-book.kind.entry' => 'wpis',

    // Zdania po czynnościach.
    'module.address-book.message.added' => 'Dopisano wpis „{entry}” ({id}).',
    'module.address-book.message.renamed' => 'Wpis {id} nazywa się odtąd „{name}”.',
    'module.address-book.message.removed' => 'Usunięto wpis „{entry}”.',
    'module.address-book.message.set' => 'Zapisano {chapter}.{field}.',
    'module.address-book.message.cleared' => 'Wyczyszczono rozdział „{chapter}” tego wpisu.',
    'module.address-book.message.edited' => 'Zmieniono wpis „{entry}”.',
    'module.address-book.message.forgotten' => 'Usunięto wartości rozdziału „{chapter}” z wpisów: {count}.',
    'module.address-book.message.nothing.forget' => 'Żaden wpis nie ma wartości w rozdziale „{chapter}”.',
    'module.address-book.message.unknown' => 'Nie ma wpisu o identyfikatorze „{entry}”.',
    'module.address-book.message.noFields' => 'Rozdział „{chapter}” nie ma dziś zadeklarowanych pól.',
    'module.address-book.message.noCommand' => 'Komenda książki adresowej jest niedostępna.',
    'module.address-book.message.copied' => 'Skopiowano wiersz wpisu „{entry}”.',

    // Powody odrzucenia — wyjątek modułu przedstawia się sam (reguła 8).
    'module.address-book.entry.id.invalid' => 'Identyfikator „{id}” nie jest dwunastoznakowym napisem szesnastkowym.',
    'module.address-book.entry.name.invalid' => 'Nazwa „{name}” ma znaki sterujące albo jest za długa.',
    'module.address-book.chapter.invalid' => 'Nazwa rozdziału „{chapter}” nie pasuje do wzorca [a-z][a-z0-9-]*.',
    'module.address-book.field.invalid' => 'Klucz pola „{field}” nie pasuje do wzorca [a-zA-Z][a-zA-Z0-9_-]*.',
    'module.address-book.value.invalid' => 'Wartość pola „{field}” ma znaki sterujące albo jest za długa.',
    'module.address-book.value.number' => 'Pole „{field}” przyjmuje liczbę, a nie „{value}”.',
    'module.address-book.value.choice' => 'Pole „{field}” przyjmuje jedną z wartości: {choices}.',
    'module.address-book.value.entry' => 'Pole „{field}” wskazuje wpis „{entry}”, którego nie ma w książce.',
    'module.address-book.field.incomplete' => 'Deklaracja pola „{field}” jest niepełna albo ma nieznany rodzaj.',
    'module.address-book.field.conflict' => 'Pole {chapter}.{field} jest już zadeklarowane inaczej — pierwsza deklaracja zostaje.',
    'module.address-book.book.unreadable' => 'Nie da się odczytać wpisów z pliku stanu.',

    // Ustawienia.
    'module.address-book.setting.order' => 'Kolejność spisu',

    // Zdarzenia (krok 46).
    'module.address-book.event.entry.added' => 'dopisano wpis',
    'module.address-book.event.entry.changed' => 'zmieniono wpis',
    'module.address-book.event.entry.removed' => 'usunięto wpis',
    'module.address-book.event.chapter.declared' => 'zapowiedziano użycie rozdziału',

    // Pomoc.
    'module.address-book.help.entry' => 'Wpis niesie nazwę i identyfikator. Tożsamością jest identyfikator, więc nazwę wolno zmienić, powtórzyć i zostawić pustą — odniesienia innych modułów tego nie zauważą.',
    'module.address-book.help.chapter' => 'Rozdział to nazwana grupa pól, deklarowana przez tego, kto z niej korzysta. Zakładka pokazuje kolumny z tej deklaracji; rozdział bez niej pokazuje surowe klucze.',
    'module.address-book.help.access' => 'Rozdział nie jest niczyją własnością: każdy moduł i sama książka czytają i piszą po wszystkich rozdziałach tymi samymi komendami i kwerendami.',
    'module.address-book.help.secret' => 'Pole rodzaju „sekret” jest zasłaniane na ekranie i nie wchodzi do wierszy spisu. Nie jest szyfrowane — plik stanu ma prawa 0600 i tyle.',
];
