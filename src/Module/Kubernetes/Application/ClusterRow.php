<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application;

use LightManager\Module\Kubernetes\Domain\ValueObject\ClusterProfile;

/**
 * Jeden wiersz spisu klastrów — wpis własny albo kontekst czytany z pliku
 * (krok 59).
 *
 * **Nazwa wiersza jest tożsamością miejsca** (D96 nr 4): dla wpisu własnego to
 * nazwa z książki, dla czytanego — nazwa kontekstu, a przy kontekstach tej
 * samej nazwy w dwóch plikach drugi dostaje przyrostek z nazwą pliku, bo dwa
 * wiersze o jednej nazwie byłyby jednym miejscem — czyli dokładnie tym błędem,
 * który ten krok usuwa.
 *
 * Ścieżka pliku **wychodzi** wierszami kwerendy (plan, punkt 8): nie jest
 * materiałem uwierzytelnienia, tylko lokalizacją, którą użytkownik sam wpisał.
 * Adres serwera nie wychodzi nadal (reguła 11w).
 */
final readonly class ClusterRow
{
    public function __construct(
        public string $name,
        public string $kubeconfig,
        public string $context,
        /** Pusty napis: przestrzeń z pliku, a w jej braku `default`. */
        public string $namespace,
        public ClusterOrigin $origin,
        public bool $current,
        /** Wpis czytany przysłonięty wpisem własnym o tej samej nazwie. */
        public bool $shadowed,
        /** Wpis własny, którego dotyczą zmiana i usunięcie; `null` dla czytanego. */
        public ?ClusterProfile $entry,
    ) {
    }
}
