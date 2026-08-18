<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application\Port;

use LightManager\Module\Kubernetes\Application\ClusterBook;

/**
 * Wynik odczytu książki klastrów (krok 59, wzorem `LoadedHostBook`).
 *
 * Port nie rzuca (reguła 8): sekcja nieczytelna wraca pustą książką z kluczem
 * powodu. Znacznik świeżości jest tu warunkiem **migracji ustawień** (plan,
 * punkt 7): pozycje `context` i `namespace` z zakładki wolno przenieść do
 * książki wyłącznie wtedy, gdy książki jeszcze nie ma — inaczej każdy start
 * nadpisywałby to, co użytkownik już w niej zmienił.
 */
final readonly class LoadedClusterBook
{
    public function __construct(
        public ClusterBook $book,
        /** Klucz katalogu z powodem; `null`, gdy odczyt się udał. */
        public ?string $problemKey = null,
        /** Czy sekcji jeszcze nigdzie nie ma — pierwsze uruchomienie tej wersji modułu. */
        public bool $fresh = false,
    ) {
    }
}
