<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Application\Port;

/**
 * Sekcja `k8s` dokumentu stanu — **wskaźnik i znacznik, nie wpisy** (krok 60).
 *
 * Do kroku 60 leżała tu książka klastrów. Wyprowadziła się do wspólnego
 * rejestru, a sekcji zostało to, co jest pamięcią **tego** modułu: które
 * miejsce jest bieżące i czy stary spis został już przeniesiony.
 *
 * Wskaźnik jest **napisem o dwóch znaczeniach** — identyfikator wpisu książki
 * albo nazwa kontekstu czytanego z `kubeconfig` — z tego samego powodu, co
 * w sekcji Dockera: kontekst z cudzego pliku identyfikatora nie ma i mieć nie
 * będzie, bo aplikacja do tych plików nie pisze.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu, krok 14).
 */
interface KubernetesStatePort
{
    /** Identyfikator wpisu albo nazwa kontekstu; pusto — użytkownik jeszcze nie wybrał. */
    public function current(): string;

    public function makeCurrent(string $value): void;

    /**
     * Stary spis klastrów z czasów, gdy książka należała do tego modułu.
     *
     * @return list<array<string, string|int>>
     */
    public function legacyClusters(): array;

    public function isMigrated(): bool;

    public function markMigrated(): void;

    /**
     * Czy sekcja jest **świeża** — nie ma w niej ani książki, ani znacznika.
     *
     * Warunek migracji pozycji ustawień z kroku 59 (`context`, `namespace`):
     * wolno je przenieść wyłącznie wtedy, gdy nie ma jeszcze czego przenosić
     * z książki, bo inaczej każdy start nadpisywałby wybór użytkownika.
     */
    public function isFresh(): bool;

    /** Gdzie leży dokument stanu — do pokazania użytkownikowi. */
    public function location(): string;
}
