<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Port;

/**
 * Sekcja `docker` dokumentu stanu — **wskaźnik i znacznik, nie wpisy**
 * (krok 60).
 *
 * Do kroku 60 leżała tu książka środowisk. Wyprowadziła się do wspólnego
 * rejestru, a sekcji zostały dwie rzeczy: **które środowisko jest bieżące**
 * i **czy stary spis został już przeniesiony**. Jedno i drugie jest pamięcią
 * tego modułu, a nie adresem — bieżące środowisko bywa przy tym kontekstem
 * klienta `docker`, czyli czymś, czego w książce nie ma i nie będzie.
 *
 * Wskaźnik jest **napisem o dwóch znaczeniach**: identyfikator wpisu książki
 * albo nazwa kontekstu czytanego z cudzego pliku. Rozstrzyga o tym spis złożony
 * z obu źródeł, nie sama sekcja — dokładnie tak, jak przed tym krokiem
 * rozstrzygał o nazwie.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu, krok 14).
 */
interface DockerStatePort
{
    /** Identyfikator wpisu albo nazwa kontekstu; pusto — użytkownik jeszcze nie wybrał. */
    public function current(): string;

    public function makeCurrent(string $value): void;

    /**
     * Stary spis środowisk z czasów, gdy książka należała do tego modułu.
     *
     * Wiersze wychodzą stąd **napisami i liczbami**, bo przenosi je do książki
     * komendami ten, kto je tu zostawił. Stare klucze zostają nietknięte
     * (migracja nieniszcząca, D103).
     *
     * @return list<array<string, string|int>>
     */
    public function legacyEnvironments(): array;

    public function isMigrated(): bool;

    public function markMigrated(): void;

    /**
     * Czy trzy pozycje ustawień rejestru przeniesiono już do książki (krok 61).
     *
     * Znacznik jest **drugi i osobny** od `isMigrated()`, bo obie migracje mają
     * różne źródła i różne terminy: tamta przenosi starą książkę środowisk
     * z pliku stanu, ta — pozycje zakładki. Jeden wspólny znacznik znaczyłby, że
     * uruchomienie sprzed kroku 61 uznaje rejestr za przeniesiony, bo środowiska
     * przeniosły się krok wcześniej.
     */
    public function isRegistryMigrated(): bool;

    public function markRegistryMigrated(): void;

    /** Gdzie leży dokument stanu — do pokazania użytkownikowi. */
    public function location(): string;
}
