<?php

declare(strict_types=1);

/**
 * Napisy modułu zadania ćwiczebnego — **plik startowy**.
 *
 * Obok tego pliku **nie ma `en.php` i to nie jest przeoczenie**: to jest ta
 * część zadania, którą złapie za ciebie bramka jakości. Katalog modułu, który
 * przetłumaczył się na jeden język, jest w tym projekcie takim samym błędem,
 * co napis wpisany wprost w kod — pilnuje tego
 * `TranslatorServiceTest::testEveryModuleCarriesTheSameKeysInEveryLanguage`,
 * a językiem odniesienia jest **angielski**.
 *
 * Klucz zaczyna się od `module.<id>.`, bo katalog modułu scala się z katalogiem
 * rdzenia i przedrostek jest jedyną rzeczą, która broni przed nadpisaniem
 * cudzego napisu.
 */
return [
    'module.czas.name' => 'Czas działania',
    'module.czas.description' => 'Mówi, od jak dawna działa to uruchomienie.',

    'module.czas.query.dzialanie' => 'sekundy od startu tego uruchomienia',
];
