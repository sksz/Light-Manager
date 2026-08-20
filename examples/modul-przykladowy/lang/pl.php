<?php

declare(strict_types=1);

/**
 * Napisy modułu przykładowego — wzorzec dla przewodnika „Nowe napisy i drugi
 * język” (`docs/pl/przewodnik/03-jak-dodac.md`).
 *
 * Trzy rzeczy do przepisania do własnego modułu:
 *
 * 1. **Klucz zaczyna się od `module.<id>.`** — katalog modułu scala się
 *    z katalogiem rdzenia, więc przedrostek jest jedyną rzeczą, która broni
 *    przed nadpisaniem cudzego napisu.
 * 2. **Oba pliki mają te same klucze.** Klucz bez tłumaczenia i tłumaczenie bez
 *    klucza są **tym samym błędem**, a pilnuje ich test bramki jakości.
 * 3. **Podstawienia idą w klamrach** (`{imie}`) i są nazwane, nie pozycyjne —
 *    tłumaczenie wolno przestawić, bo szyk zdania bywa w każdym języku inny.
 */
return [
    'module.przyklad.name' => 'Przykład',
    'module.przyklad.description' => 'Moduł wzorcowy dla przewodnika dewelopera.',

    'module.przyklad.setting.ton' => 'Ton powitania',

    'module.przyklad.command.powitanie' => 'przywitaj się',
    'module.przyklad.argument.imie' => 'imię',

    'module.przyklad.query.stan' => 'ton powitania ustawiony w module',

    'module.przyklad.message.zwykle' => 'Dzień dobry, {imie}.',
    'module.przyklad.message.glosne' => 'DZIEŃ DOBRY, {imie}!',
];
