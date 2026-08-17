<?php

declare(strict_types=1);

/*
 * Napisy modułu „Przeglądarka plików” — polski.
 *
 * Plik leży w katalogu modułu, a nie w `lang/` rdzenia, i to jest reguła, nie
 * wygoda: moduł ma dać się dopisać bez dotykania rdzenia, a napisy są jego
 * częścią tak samo jak kod.
 *
 * **Każdy klucz musi zaczynać się od `module.browser.`** — katalog przyjmuje
 * wyłącznie takie i pomija resztę. Kolizja z kluczem rdzenia jest przez to
 * niemożliwa z konstrukcji, a źródło napisu widać po samej nazwie klucza.
 *
 * Większość tych napisów stała do kroku 20 w katalogu rdzenia (`browser.*`,
 * `help.key.open`, `preview.*`, `problem.directory.*`). Zeszły tutaj razem
 * z kodem, który je pokazuje, i **co do znaku w tym samym brzmieniu** — krok 21
 * jest przenosinami, nie przeprojektowaniem.
 */

return [
    'module.browser.name' => 'Przeglądarka plików',
    'module.browser.description' => 'Nawigacja po katalogach wraz z podglądem miniatur — moduł domyślny.',

    // Etykieta strefy środkowej; do kroku 20 klucz rdzenia `layout.zone.files`.
    'module.browser.zone.files' => 'PLIKI',

    // Treść ekranu.
    'module.browser.empty' => '(katalog jest pusty)',
    'module.browser.hidden' => '• ukryte',

    // Filtr listy (krok 30). Znacznik w pasie ścieżki jest jedynym śladem
    // zawężenia po zamknięciu pola, więc niesie wpisany fragment, nie samo słowo.
    'module.browser.filter.zone' => 'FILTR',
    'module.browser.filter.prompt' => 'szukaj: ',
    'module.browser.filter.marker' => '• filtr: {fragment}',
    'module.browser.filter.none' => '(nic nie pasuje do filtra)',
    'module.browser.filter.key.accept' => 'zostaw listę zawężoną',
    'module.browser.filter.key.cancel' => 'zdejmij filtr i wróć do wpisu',

    // Zaznaczenie wielokrotne (krok 43). Znacznik zajmuje jedną kolumnę przed
    // nazwą i pojawia się dopiero wtedy, gdy zbiór nie jest pusty.
    'module.browser.marked.marker' => '•',
    // Podsumowanie stoi w pasie ścieżki obok numeru wpisu, więc jest krótkie:
    // ile z ilu i ile to razem waży. Mianownik jest liczbą wpisów **katalogu**,
    // a nie widocznej listy — zbiór przeżywa zawężenie filtrem.
    'module.browser.marked.summary' => '• {count} z {total} · {size}',
    // Wariant z katalogami mówi wprost, czego suma nie obejmuje: rozmiaru
    // katalogu nie zna nikt poza `du`, więc suma milcząca o pominięciu kłamie.
    // Skrót „kat.” zamiast odmienianego rzeczownika, bo pas ścieżki liczy się
    // w kolumnach, a nie dlatego, że katalog form mnogich nie umie.
    'module.browser.marked.summary.dirs' => '• {count} z {total} · {size} bez {dirs} kat.',

    // Kopiowanie zaznaczonych wpisów do schowka (krok 57). Ścieżki, nie nazwy:
    // napis wklejony do innej aplikacji ma się nadawać do użycia.
    'module.browser.clipboard.copied' => [
        'Skopiowano ścieżkę {count} zaznaczonego wpisu.',
        'Skopiowano ścieżki {count} zaznaczonych wpisów.',
        'Skopiowano ścieżki {count} zaznaczonych wpisów.',
    ],

    // Pozycje zakładki ustawień modułu.
    'module.browser.setting.showHidden' => 'Pokazuj wpisy ukryte',
    'module.browser.setting.split' => 'Podział na dwa panele',
    // Napis mówi o panelach, a nie o osi: „pionowy” bywa czytane obiema
    // stronami — raz jako kierunek granicy, raz jako ułożenie paneli.
    'module.browser.setting.splitVertical' => 'Panele obok siebie',
    'module.browser.setting.details' => 'Kolumny szczegółów (data, prawa)',
    'module.browser.setting.columnHeader' => 'Nazwy kolumn nad listą',
    // Wartości tej pozycji ekran ustawień pokazuje surowo, więc „bez limitu”
    // niesie znak nieskończoności — czytelny bez tłumaczenia.
    'module.browser.setting.treeDepth' => 'Poziomy drzewa (Ctrl+T)',
    'module.browser.setting.askBeforeDelete' => 'Pytaj przed usunięciem',
    // Krok 44: dwie drogi usunięcia i stos cofnięć. Katalog kosza pusty znaczy
    // „kosz środowiska graficznego” — rozwiązuje go port, nie ta pozycja.
    'module.browser.setting.deleteToTrash' => 'Usuwaj do kosza (F8, Delete)',
    'module.browser.setting.trashDirectory' => 'Katalog kosza (pusty: systemowy)',
    'module.browser.setting.undoDepth' => 'Głębokość stosu cofnięć (F3)',
    'module.browser.setting.splitFraction' => 'Szerokość lewego panelu (%)',

    // Nagłówki kolumn listy (krok 27). Widać je wyłącznie po włączeniu
    // przełącznika „Nazwy kolumn nad listą” — sama treść kolumn mówi za siebie.
    'module.browser.column.name' => 'Nazwa',
    'module.browser.column.size' => 'Rozmiar',
    'module.browser.column.modified' => 'Zmieniony',
    'module.browser.column.permissions' => 'Prawa',

    // Pas podglądu miniatur.
    'module.browser.preview.unreadable' => 'Nie udało się odczytać obrazu.',
    'module.browser.preview.tooLarge' => 'Plik przekracza limit {limit} MB — bez podglądu.',
    'module.browser.preview.tooManyPixels' => '{dimensions} — obraz przekracza limit {limit} Mpx.',

    // Klawisze ekranu — źródło spisu w oknie pomocy i podpowiedzi.
    'module.browser.help.open' => 'wejście do katalogu',
    'module.browser.help.up' => 'katalog wyżej',
    'module.browser.help.hidden' => 'pokaż lub ukryj wpisy ukryte',
    'module.browser.help.focus' => 'przejście do drugiego panelu',
    'module.browser.help.filter' => 'zawężenie listy fragmentem nazwy',
    'module.browser.help.filter.clear' => 'zdjęcie filtra',

    // Zaznaczanie (krok 43) — klawisze **listy**, w drzewie ich nie ma.
    'module.browser.help.mark' => 'zaznaczenie wpisu i przejście niżej',
    'module.browser.help.invert' => 'odwrócenie zaznaczenia na widocznej liście',
    'module.browser.help.marked.clear' => 'zdjęcie zaznaczenia',

    // Drzewo katalogów (krok 31). Opisy strzałek widać wyłącznie w panelu
    // pokazującym drzewo — w liście te same klawisze znaczą co innego.
    'module.browser.help.rename' => 'zmiana nazwy wpisu',
    'module.browser.help.copy' => 'kopiowanie wpisu',
    'module.browser.help.move' => 'przeniesienie wpisu',
    'module.browser.help.mkdir' => 'nowy katalog',
    'module.browser.help.delete' => 'usunięcie trwałe wpisu',
    // Druga droga usunięcia (krok 44): opisy klawisza gołego i z Shiftem
    // wybiera ekran wedle ustawienia, więc oba klucze muszą stać w katalogu.
    'module.browser.help.trash' => 'przeniesienie wpisu do kosza',
    'module.browser.help.markRange' => 'zaznaczanie zakresem',
    'module.browser.help.undo' => 'cofnięcie ostatniej operacji',
    'module.browser.help.undoView' => 'stos cofnięć',
    'module.browser.help.tree' => 'panel jako drzewo albo lista',
    'module.browser.help.tree.expand' => 'rozwinięcie gałęzi',
    'module.browser.help.tree.collapse' => 'zwinięcie gałęzi lub poziom wyżej',

    // Krótkie opisy dla paska stanu (krok 40) i nazwy miejsc, w których staje
    // ognisko. Przy podziale nazywa się **panel**, bo to on odróżnia jedno miejsce
    // od drugiego; przy jednym panelu — **widok**, bo to on rozstrzyga, co znaczą
    // strzałki poziome.
    'module.browser.help.open.short' => 'katalog',
    'module.browser.help.up.short' => 'wyżej',
    'module.browser.help.hidden.short' => 'ukryte',
    'module.browser.help.focus.short' => 'panel',
    'module.browser.help.filter.short' => 'filtr',
    'module.browser.help.filter.clear.short' => 'bez filtra',
    'module.browser.help.mark.short' => 'zaznacz',
    'module.browser.help.invert.short' => 'odwróć',
    'module.browser.help.marked.clear.short' => 'bez zaznaczeń',
    'module.browser.help.rename.short' => 'nazwa',
    'module.browser.help.copy.short' => 'kopiuj',
    'module.browser.help.move.short' => 'przenieś',
    'module.browser.help.mkdir.short' => 'katalog',
    'module.browser.help.delete.short' => 'usuń',
    'module.browser.help.trash.short' => 'kosz',
    'module.browser.help.markRange.short' => 'zakres',
    'module.browser.help.undo.short' => 'cofnij',
    'module.browser.help.undoView.short' => 'cofnięcia',
    'module.browser.help.tree.short' => 'drzewo',
    'module.browser.help.tree.expand.short' => 'rozwiń',
    'module.browser.help.tree.collapse.short' => 'zwiń',
    'module.browser.focus.list' => 'Lista',
    'module.browser.focus.tree' => 'Drzewo',
    'module.browser.focus.left' => 'Panel lewy',
    'module.browser.focus.right' => 'Panel prawy',
    'module.browser.focus.top' => 'Panel górny',
    'module.browser.focus.bottom' => 'Panel dolny',
    // Liczba poziomów odmienia zdanie, stąd trzy formy. Pierwsza jest dziś
    // nieosiągalna (najmniejszy wybór to dwa), ale reguła mnoga polskiego ma trzy
    // formy i katalog ma je podać w komplecie.
    'module.browser.tree.depth' => [
        'Drzewo pokazuje najwyżej {count} poziom — zmienisz to w ustawieniach modułu.',
        'Drzewo pokazuje najwyżej {count} poziomy — zmienisz to w ustawieniach modułu.',
        'Drzewo pokazuje najwyżej {count} poziomów — zmienisz to w ustawieniach modułu.',
    ],

    // Komendy modułu. Trzy ostatnie doszły w kroku 32: nazywają czynności,
    // które przeglądarka miała dotąd wyłącznie pod klawiszem.
    'module.browser.command.jump' => 'przejdź do wskazanego katalogu',
    'module.browser.command.open' => 'wejdź do zaznaczonego katalogu',
    'module.browser.command.hidden' => 'pokaż lub ukryj wpisy ukryte',
    'module.browser.command.tree' => 'panel jako drzewo albo lista',
    'module.browser.query.entries' => 'wpisy katalogu widocznego w panelu',
    'module.browser.query.selection' => 'wpis pod kursorem wraz z jego atrybutami',
    'module.browser.query.marked' => 'nazwy i ścieżki zaznaczonych wpisów',
    'module.browser.query.cwd' => 'ścieżki obu paneli wraz z czynnym',
    'module.browser.query.panes' => 'układ paneli: widok, filtr, zaznaczenie',
    'module.browser.query.tree' => 'drzewo katalogów panelu, spłaszczone',
    'module.browser.query.undo' => 'stos operacji wraz z odwracalnością',
    'module.browser.query.argument.pane' => 'panel (0 albo 1)',
    // Dwie komendy z argumentem — pierwsze w projekcie (krok 41). Nazwa idzie
    // w wierszu, bo komenda nie umie otworzyć okna nakładanego (D75, nr 5).
    'module.browser.command.rename' => 'zmień nazwę zaznaczonego wpisu',
    'module.browser.command.mkdir' => 'utwórz katalog w katalogu panelu',
    'module.browser.command.delete' => 'usuń zaznaczony wpis',
    // Dwie komendy kroku 42. Ścieżka jest opcjonalna: bez niej otwiera się okno
    // z katalogiem drugiego panelu, czyli to samo, co pod klawiszem.
    'module.browser.command.copy' => 'skopiuj zaznaczony wpis do wskazanego katalogu',
    'module.browser.command.move' => 'przenieś zaznaczony wpis do wskazanego katalogu',
    'module.browser.argument.path' => 'ścieżka',
    'module.browser.argument.name' => 'nazwa',
    'module.browser.jump.failed' => 'Nie można otworzyć katalogu „{path}”.',
    'module.browser.open.failed' => 'Nie można otworzyć zaznaczonego katalogu.',
    'module.browser.open.notDirectory' => 'Zaznaczony wpis nie jest katalogiem.',
    'module.browser.hidden.failed' => 'Nie można odczytać katalogu ponownie — ustawienie zostaje bez zmian.',

    // Czynności zmieniające dysk (krok 41): tytuły okien i zdania o skutku.
    'module.browser.rename.title' => 'Nowa nazwa dla „{name}”',
    'module.browser.rename.done' => 'Nazwa zmieniona na „{name}”.',
    'module.browser.mkdir.title' => 'Nazwa nowego katalogu',
    'module.browser.mkdir.done' => 'Katalog „{name}” utworzony.',
    'module.browser.delete.confirm.file' => 'Usunąć „{name}” bezpowrotnie?',
    // Liczba stoi po dwukropku, a nie w odmienianym zdaniu: ta sama liczba
    // pojawia się potem w pasku postępu, więc obie mówią to samo. (Do kroku 43
    // powodem był ponadto brak form mnogich w oknie potwierdzenia — dziś okno
    // je umie, ale to zdanie i tak ich nie potrzebuje.)
    'module.browser.delete.confirm.tree' => 'Usunąć „{name}” wraz z zawartością? Do usunięcia: {count}.',
    // Zbiór pyta **liczbą**, a nie nazwą pierwszego z dwunastu (krok 43). Formy
    // mnogie są tu naprawdę potrzebne: „2 wpisy” i „5 wpisów” to nie jest ten sam
    // napis z podstawioną cyfrą. Pierwsza forma nie ma prawa się pojawić — zbiór
    // liczący jeden wpis pyta zdaniem z nazwą — ale stoi, bo katalog wymaga
    // kompletu trzech.
    'module.browser.delete.confirm.many' => [
        'Usunąć zaznaczony wpis bezpowrotnie?',
        'Usunąć {count} zaznaczone wpisy bezpowrotnie?',
        'Usunąć {count} zaznaczonych wpisów bezpowrotnie?',
    ],
    // Wariant dla zbioru z katalogami: wpisów do usunięcia jest więcej niż
    // zaznaczonych, bo znika także ich zawartość — i to jest liczba, którą
    // użytkownik ma zobaczyć przed „tak”.
    'module.browser.delete.confirm.manyTrees' => [
        'Usunąć zaznaczony wpis wraz z zawartością? Do usunięcia: {total}.',
        'Usunąć {count} zaznaczone wpisy wraz z zawartością? Do usunięcia: {total}.',
        'Usunąć {count} zaznaczonych wpisów wraz z zawartością? Do usunięcia: {total}.',
    ],
    'module.browser.delete.counting' => 'Liczenie zawartości „{name}”',
    'module.browser.delete.deleting' => 'Usuwanie „{name}”',
    // Tytuły okien pracy dla zbioru. Liczba stoi po dwukropku, więc zdanie nie
    // odmienia się przez nią — okno postępu form mnogich nie zna i nie musi.
    'module.browser.delete.counting.many' => 'Liczenie zawartości zaznaczonych: {count}',
    'module.browser.delete.deleting.many' => 'Usuwanie zaznaczonych: {count}',
    'module.browser.delete.doneOne' => 'Usunięto „{name}”.',
    'module.browser.delete.needsOverlay' => 'Usunięcie wymaga potwierdzenia — użyj klawisza F8 albo menu F9.',
    'module.browser.delete.done' => [
        'Usunięto {count} wpis.',
        'Usunięto {count} wpisy.',
        'Usunięto {count} wpisów.',
    ],
    'module.browser.delete.stopped' => [
        'Przerwano — usunięto {count} wpis z {total}.',
        'Przerwano — usunięto {count} wpisy z {total}.',
        'Przerwano — usunięto {count} wpisów z {total}.',
    ],
    'module.browser.delete.abandoned' => 'Liczenie przerwane — dysk nietknięty.',

    // Kopiowanie i przenoszenie (krok 42). Tytuły okien kończą się dwukropkiem,
    // bo pod nimi stoi pole ze ścieżką — a nie pytanie o tak albo nie.
    'module.browser.copy.title' => 'Skopiuj „{name}” do:',
    'module.browser.move.title' => 'Przenieś „{name}” do:',
    'module.browser.copy.progress' => 'Kopiowanie „{name}”',
    'module.browser.move.progress' => 'Przenoszenie „{name}”',
    'module.browser.transfer.counting' => 'Liczenie zawartości „{name}”',
    // Warianty dla zbioru (krok 43): liczba zamiast nazwy pierwszego wpisu.
    'module.browser.copy.title.many' => 'Skopiuj zaznaczone ({count}) do:',
    'module.browser.move.title.many' => 'Przenieś zaznaczone ({count}) do:',
    'module.browser.copy.progress.many' => 'Kopiowanie zaznaczonych: {count}',
    'module.browser.move.progress.many' => 'Przenoszenie zaznaczonych: {count}',
    'module.browser.transfer.counting.many' => 'Liczenie zawartości zaznaczonych: {count}',
    'module.browser.transfer.abandoned' => 'Liczenie przerwane — dysk nietknięty.',
    // Licznik idzie w środek paska, więc jest krótki: rozmiar, a po kropce
    // wypunktowującej — który to wpis z ilu.
    'module.browser.transfer.counter' => '{done} z {total} · {entry}/{entries}',
    'module.browser.transfer.counter.size' => '{done} z {total}',
    'module.browser.transfer.needsOverlay' => 'Kopiowanie wymaga okna — użyj klawisza F5 albo F6 lub menu F9.',

    // Kolizja nazw: sześć odpowiedzi, bo „do wszystkich” jest inną odpowiedzią,
    // a nie przełącznikiem przy tamtych czterech (D79, nr 4).
    'module.browser.transfer.collision' => 'W celu jest już „{name}”',
    'module.browser.transfer.overwrite' => 'Nadpisz',
    'module.browser.transfer.overwriteAll' => 'Nadpisz wszystkie',
    'module.browser.transfer.skip' => 'Pomiń',
    'module.browser.transfer.skipAll' => 'Pomiń wszystkie',
    'module.browser.transfer.rename' => 'Zapisz pod inną nazwą',
    'module.browser.transfer.abort' => 'Przerwij',
    'module.browser.transfer.newName' => 'Nowa nazwa dla „{name}”',
    'module.browser.copy.done' => [
        'Skopiowano {count} wpis.',
        'Skopiowano {count} wpisy.',
        'Skopiowano {count} wpisów.',
    ],
    'module.browser.move.done' => [
        'Przeniesiono {count} wpis.',
        'Przeniesiono {count} wpisy.',
        'Przeniesiono {count} wpisów.',
    ],
    'module.browser.copy.stopped' => [
        'Przerwano — skopiowano {count} wpis z {total}.',
        'Przerwano — skopiowano {count} wpisy z {total}.',
        'Przerwano — skopiowano {count} wpisów z {total}.',
    ],
    'module.browser.move.stopped' => [
        'Przerwano — przeniesiono {count} wpis z {total}.',
        'Przerwano — przeniesiono {count} wpisy z {total}.',
        'Przerwano — przeniesiono {count} wpisów z {total}.',
    ],

    // Kosz (krok 44, D81): pytania, skutki i droga przez inny system plików.
    // Pytanie przed koszem jest zwykłe, nie groźne — czynność jest odwracalna.
    'module.browser.trash.confirm.file' => 'Przenieść „{name}” do kosza?',
    'module.browser.trash.confirm.many' => [
        'Przenieść zaznaczony wpis do kosza?',
        'Przenieść {count} zaznaczone wpisy do kosza?',
        'Przenieść {count} zaznaczonych wpisów do kosza?',
    ],
    'module.browser.trash.doneOne' => 'Przeniesiono „{name}” do kosza.',
    'module.browser.trash.done' => [
        'Przeniesiono {count} wpis do kosza.',
        'Przeniesiono {count} wpisy do kosza.',
        'Przeniesiono {count} wpisów do kosza.',
    ],
    'module.browser.trash.stopped' => [
        'Przerwano — w koszu stanął {count} wpis z {total}.',
        'Przerwano — w koszu stanęły {count} wpisy z {total}.',
        'Przerwano — w koszu stanęło {count} wpisów z {total}.',
    ],
    'module.browser.trash.abandoned' => 'Nic nie zostało przeniesione — dysk nietknięty.',
    // Wpis spoza systemu plików kosza: ostrzeżenie i trzy odpowiedzi (D81, nr 5).
    // „Przerwij” stoi ostatnie, bo Esc znaczy w oknie wyboru odpowiedź ostatnią.
    'module.browser.trash.foreign' => '„{name}” leży na innym systemie plików niż kosz',
    'module.browser.trash.foreign.many' => 'Wpisy na innym systemie plików niż kosz: {count}',
    'module.browser.trash.foreign.copy' => 'Skopiuj do kosza',
    'module.browser.trash.foreign.delete' => 'Usuń trwale',
    'module.browser.trash.foreign.abort' => 'Przerwij',
    // Tytuły okien pracy przy kopiowaniu do kosza — jak przy przenoszeniu.
    'module.browser.trash.counting' => 'Liczenie zawartości „{name}”',
    'module.browser.trash.counting.many' => 'Liczenie zawartości zaznaczonych: {count}',
    'module.browser.trash.progress' => 'Przenoszenie „{name}” do kosza',
    'module.browser.trash.progress.many' => 'Przenoszenie do kosza: {count}',

    // Stos cofnięć (krok 44, D81 nr 6–8): widok, skutki i odmowy.
    'module.browser.undo.title' => 'Stos cofnięć',
    'module.browser.undo.empty' => 'Nie ma czego cofnąć.',
    'module.browser.undo.irreversible' => 'Tej operacji nie da się cofnąć.',
    'module.browser.undo.key.run' => 'cofnięcie wybranej operacji',
    'module.browser.undo.key.pick' => 'wybór operacji',
    'module.browser.undo.key.close' => 'zamknięcie okna',
    // Zdanie o skutku mówi **co** cofnięto, nie „cofnięto” — kursor staje na
    // wpisie przywróconym, a zdanie nazywa go po imieniu.
    'module.browser.undo.done.rename' => 'Przywrócono nazwę „{name}”.',
    'module.browser.undo.done.mkdir' => 'Usunięto pusty katalog „{name}”.',
    'module.browser.undo.done.trashOne' => 'Przywrócono „{name}” z kosza.',
    'module.browser.undo.done.trash' => [
        'Przywrócono {count} wpis z kosza.',
        'Przywrócono {count} wpisy z kosza.',
        'Przywrócono {count} wpisów z kosza.',
    ],
    'module.browser.undo.done.move' => [
        'Przywrócono {count} wpis na poprzednie miejsce.',
        'Przywrócono {count} wpisy na poprzednie miejsce.',
        'Przywrócono {count} wpisów na poprzednie miejsce.',
    ],
    // Wiersze widoku: operacja i jej przedmiot. Nieodwracalne stoją wyszarzone,
    // ale stoją — lista mówi też, co się właściwie wydarzyło (D81, nr 8).
    'module.browser.undo.entry.rename' => 'Zmiana nazwy: {from} → {to}',
    'module.browser.undo.entry.mkdir' => 'Nowy katalog: {name}',
    'module.browser.undo.entry.trash' => 'Do kosza: {name}',
    'module.browser.undo.entry.trash.many' => [
        'Do kosza: {count} wpis',
        'Do kosza: {count} wpisy',
        'Do kosza: {count} wpisów',
    ],
    'module.browser.undo.entry.move' => 'Przeniesienie: {name}',
    'module.browser.undo.entry.move.many' => [
        'Przeniesienie: {count} wpis',
        'Przeniesienie: {count} wpisy',
        'Przeniesienie: {count} wpisów',
    ],
    'module.browser.undo.entry.copy' => 'Kopiowanie: {name}',
    'module.browser.undo.entry.copy.many' => [
        'Kopiowanie: {count} wpis',
        'Kopiowanie: {count} wpisy',
        'Kopiowanie: {count} wpisów',
    ],
    'module.browser.undo.entry.delete' => 'Usunięcie trwałe: {name}',
    'module.browser.undo.entry.delete.many' => [
        'Usunięcie trwałe: {count} wpis',
        'Usunięcie trwałe: {count} wpisy',
        'Usunięcie trwałe: {count} wpisów',
    ],

    // Nazwa wpisana przez użytkownika — każdy powód odmowy ma własne zdanie.
    'module.browser.name.empty' => 'Nazwa nie może być pusta.',
    'module.browser.name.reserved' => 'Nazwa „{name}” należy do systemu plików.',
    'module.browser.name.separator' => 'Nazwa nie może zawierać ukośnika — to nazwa, nie ścieżka.',
    'module.browser.name.tooLong' => 'Nazwa jest dłuższa niż {limit} bajtów.',

    // Zdania dla użytkownika składane z wyjątków modułu (`DescribesProblem`).
    'module.browser.problem.unreadable' => 'Nie można odczytać katalogu „{path}”.',
    'module.browser.problem.invalidPath' => '„{path}” nie jest bezwzględną ścieżką katalogu.',
    'module.browser.problem.fallback' => 'Nie można odczytać katalogu „{requested}” — otwarto „{opened}”.',
    'module.browser.problem.noSelection' => 'Nie ma zaznaczonego wpisu.',
    'module.browser.problem.noEntry' => 'W tym katalogu nie ma wpisu „{name}”.',

    // Zdarzenia, które moduł ogłasza reszcie aplikacji (krok 46). Nazwy mówią,
    // **co się stało**, i nie wiedzą, że ktokolwiek ich słucha. Mieszczą się przy
    // tym w **27 znakach** — tyle daje kolumna spisu w panelu podziału przy progu
    // 100 kolumn (`EffectList`), a nazwa ucięta wielokropkiem przestałaby
    // odróżniać czynność udaną od nieudanej.
    'module.browser.event.cursor.moved' => 'Ruch kursora',
    'module.browser.event.directory.entered' => 'Wejście do katalogu',
    'module.browser.event.entry.marked' => 'Zaznaczenie wpisu',
    'module.browser.event.rename.done' => 'Zmiana nazwy: udana',
    'module.browser.event.rename.failed' => 'Zmiana nazwy: nieudana',
    'module.browser.event.mkdir.done' => 'Nowy katalog: udany',
    'module.browser.event.mkdir.failed' => 'Nowy katalog: nieudany',
    'module.browser.event.copy.done' => 'Kopiowanie: zakończone',
    'module.browser.event.copy.failed' => 'Kopiowanie: nieudane',
    'module.browser.event.move.done' => 'Przeniesienie: zakończone',
    'module.browser.event.move.failed' => 'Przeniesienie: nieudane',
    'module.browser.event.trash.done' => 'Kosz: zakończony',
    'module.browser.event.trash.failed' => 'Kosz: nieudany',
    'module.browser.event.delete.done' => 'Usunięcie trwałe: udane',
    'module.browser.event.delete.failed' => 'Usunięcie trwałe: nieudane',
    'module.browser.event.undo.done' => 'Cofnięcie: udane',
    'module.browser.event.undo.failed' => 'Cofnięcie: nieudane',

    // Własna część zakładki pomocy — to, czego z deklaracji wyczytać się nie da.
    'module.browser.help.default' => 'Przeglądarka jest modułem domyślnym: Esc wraca do niej z każdego '
        . 'innego ekranu, a wyłączyć się jej nie da.',
    'module.browser.help.jump' => 'Komenda browser.jump podpowiada katalogi z dysku — Tab uzupełnia ścieżkę.',
];
