<?php

declare(strict_types=1);

/*
 * Napisy modułu „Opis pliku” — polski.
 *
 * Plik leży w katalogu modułu, a nie w `lang/` rdzenia, i to jest reguła, nie
 * wygoda: moduł ma dać się dopisać bez dotykania rdzenia, a napisy są jego
 * częścią tak samo jak kod.
 *
 * **Każdy klucz musi zaczynać się od `module.file-info.`** — katalog przyjmuje
 * wyłącznie takie i pomija resztę. Kolizja z kluczem rdzenia jest przez to
 * niemożliwa z konstrukcji, a źródło napisu widać po samej nazwie klucza.
 */

return [
    'module.file-info.name' => 'Opis pliku',
    'module.file-info.description' => 'Pokazuje, czym jest zaznaczony plik — opisem polecenia „file”.',

    // Pozycje zakładki ustawień modułu.
    'module.file-info.setting.timeout' => 'Limit czasu polecenia (s)',
    'module.file-info.setting.arguments' => 'Dodatkowe argumenty',

    // Treść ekranu.
    'module.file-info.nothing' => '(nie zaznaczono pliku)',
    'module.file-info.empty' => 'Brak opisu.',
    'module.file-info.execDisabled' => 'Funkcja proc_open() jest wyłączona — nie można uruchomić '
        . 'polecenia „file”.',
    'module.file-info.failed' => 'Polecenie „file” zakończyło się błędem.',
    'module.file-info.failedWith' => 'Polecenie „file” zakończyło się błędem: {detail}',
    'module.file-info.timedOut' => 'Polecenie „file” nie odpowiedziało w ciągu {seconds} s — przerwano.',

    // Komenda modułu.
    'module.file-info.command.jump' => 'przejdź do wskazanego katalogu',
    'module.file-info.argument.path' => 'ścieżka',
    'module.file-info.jump.failed' => 'Nie można otworzyć katalogu „{path}”.',

    // Własna część zakładki pomocy — to, czego z deklaracji wyczytać się nie da.
    'module.file-info.help.enter' => 'Opis dotyczy wpisu zaznaczonego na liście plików; katalogów nie opisuje.',
    'module.file-info.help.jump' => 'Komenda file-info.jump podpowiada katalogi z dysku — Tab uzupełnia ścieżkę.',
];
