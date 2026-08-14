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

    // Drzewo katalogów (krok 31). Opisy strzałek widać wyłącznie w panelu
    // pokazującym drzewo — w liście te same klawisze znaczą co innego.
    'module.browser.help.tree' => 'panel jako drzewo albo lista',
    'module.browser.help.tree.expand' => 'rozwinięcie gałęzi',
    'module.browser.help.tree.collapse' => 'zwinięcie gałęzi lub poziom wyżej',
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
    'module.browser.argument.path' => 'ścieżka',
    'module.browser.jump.failed' => 'Nie można otworzyć katalogu „{path}”.',
    'module.browser.open.failed' => 'Nie można otworzyć zaznaczonego katalogu.',
    'module.browser.open.notDirectory' => 'Zaznaczony wpis nie jest katalogiem.',
    'module.browser.hidden.failed' => 'Nie można odczytać katalogu ponownie — ustawienie zostaje bez zmian.',

    // Zdania dla użytkownika składane z wyjątków modułu (`DescribesProblem`).
    'module.browser.problem.unreadable' => 'Nie można odczytać katalogu „{path}”.',
    'module.browser.problem.invalidPath' => '„{path}” nie jest bezwzględną ścieżką katalogu.',
    'module.browser.problem.fallback' => 'Nie można odczytać katalogu „{requested}” — otwarto „{opened}”.',

    // Własna część zakładki pomocy — to, czego z deklaracji wyczytać się nie da.
    'module.browser.help.default' => 'Przeglądarka jest modułem domyślnym: Esc wraca do niej z każdego '
        . 'innego ekranu, a wyłączyć się jej nie da.',
    'module.browser.help.jump' => 'Komenda browser.jump podpowiada katalogi z dysku — Tab uzupełnia ścieżkę.',
];
