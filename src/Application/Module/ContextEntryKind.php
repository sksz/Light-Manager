<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Czym jest to, co użytkownik ma zaznaczone — tyle, ile moduł ma prawo wiedzieć
 * o cudzym zaznaczeniu.
 *
 * Rodzaj jest enumem, a nie typem z dziedziny (`EntryType`), bo `ModuleContext`
 * ma nie wymieniać z modułem niczego, co należy do innego modułu ani do
 * agregatu, który w kroku 21 zejdzie z rdzenia do przeglądarki (D40, P5).
 */
enum ContextEntryKind
{
    /** Nic nie jest zaznaczone: katalog pusty, nieczytelny albo bez wydawcy kontekstu. */
    case None;

    case File;

    case Directory;
}
