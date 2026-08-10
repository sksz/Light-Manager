<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

/**
 * Katalog motywów dostępnych w tym uruchomieniu.
 *
 * Port zakładamy dopiero w kroku 14, bo dopiero teraz `Application` naprawdę
 * woła motyw: ekran ustawień musi wiedzieć, po jakich nazwach chodzi
 * przełącznik. Same wartości kolorów zostają po stronie renderowania — warstwa
 * aplikacji ma prawo znać nazwy palet, nigdy ich zawartości
 * ([00-decyzje.md](../../../docs/plans/00-decyzje.md), D17).
 */
interface ThemePort
{
    /** @return list<string> nazwy motywów w kolejności, w jakiej chodzi przełącznik */
    public function names(): array;

    public function has(string $name): bool;
}
