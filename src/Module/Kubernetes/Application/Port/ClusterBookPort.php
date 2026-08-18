<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application\Port;

use LightManager\Module\Kubernetes\Application\ClusterBook;

/**
 * Zapis i odczyt książki klastrów — sekcja `k8s` dokumentu stanu (krok 59).
 *
 * Pierwsza książka projektu, która **nie miała nigdy własnego pliku**: rodzi
 * się od razu w `~/.light-manager/state.json`, w sekcji modułu (wynik przeglądu
 * 15e, D103). Nieznane klucze sekcji przeżywają zapis od pierwszego dnia —
 * następne kroki dopiszą swoje **kluczami tej samej sekcji**, nie drugim
 * miejscem.
 */
interface ClusterBookPort
{
    public function load(): LoadedClusterBook;

    public function save(ClusterBook $book): void;

    /** Gdzie dokument leży — ekran pokazuje to, gdy spis jest pusty. */
    public function location(): string;
}
