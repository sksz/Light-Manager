<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Port;

use LightManager\Module\Docker\Application\ContextEntry;

/**
 * Konteksty klienta `docker` — czytane pracą tłową, nigdy w klatce (krok 58).
 *
 * Kształt z D46: zamówienie, które nie czeka ani chwili, posuwanie o takt,
 * stan do obejrzenia. **Brak klienta nie jest awarią** (D96 nr 3): lista
 * schodzi wtedy do wpisów własnych plus gniazda lokalnego, a `problemKey()`
 * milczy — moduł nie wymaga klienta do niczego poza compose i nie zacznie.
 */
interface ContextCatalogPort
{
    /** Zamawia świeży odczyt. Wołanie w trakcie trwającego nie robi nic. */
    public function refresh(): void;

    /** Posuwa odczyt o takt. **Nigdy nie blokuje.** */
    public function advance(): void;

    /** @return list<ContextEntry> ostatnia przyjęta odpowiedź; pusta przed pierwszą */
    public function all(): array;

    public function isReading(): bool;

    /** Powód, dla którego odczyt się nie udał; brak klienta to nie powód. */
    public function problemKey(): ?string;
}
