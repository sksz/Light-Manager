<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application\Port;

/**
 * Sekcja `ssh` dokumentu stanu — **pamięć modułu, nie wpisy** (krok 60).
 *
 * Do kroku 60 leżała tu książka hostów. Wyprowadziła się do wspólnego rejestru,
 * a sekcji zostało dokładnie to, co jest pamięcią **tego** modułu i niczyją
 * więcej: zapamiętany katalog zdalny per wpis oraz znacznik przeniesienia.
 *
 * **Zapamiętany katalog nie jest polem rozdziału i nie ma nim być.** Rozdział
 * `ssh` niesie adres, użytkownika i materiał uwierzytelnienia — czyli to, po co
 * ktokolwiek pyta książkę. Ścieżka, na której skończyło się poprzednie
 * oglądanie, nie jest adresem; zapisuje się ją **przy każdej zmianie
 * katalogu**, więc w książce znaczyłaby zapis wspólnego dokumentu i zdarzenie
 * `entry.changed` kilka razy na sekundę przy zwykłym chodzeniu po drzewie.
 *
 * Klucz jest **identyfikatorem wpisu książki**, a nie jego nazwą: nazwę wolno
 * zmienić, a pamięć ma za nią pójść.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu, krok 14).
 */
interface SshStatePort
{
    /**
     * Stary spis hostów z czasów, gdy książka należała do tego modułu — do
     * przeniesienia do wspólnego rejestru.
     *
     * Pusta lista znaczy „nie ma czego przenosić": albo przeniesienie już się
     * odbyło, albo tej wersji modułu nikt wcześniej nie uruchamiał. **Starych
     * kluczy nikt nie kasuje** (migracja nieniszcząca, D103) — o tym, że
     * przeniesienie się odbyło, mówi osobny znacznik.
     *
     * @return list<array<string, string|int>>
     */
    public function legacyHosts(): array;

    public function isMigrated(): bool;

    public function markMigrated(): void;

    /** Katalog, na którym skończyło się poprzednie oglądanie tego wpisu. */
    public function lastDirectory(string $entryId): ?string;

    /**
     * Zapamiętuje katalog. Wołane **przy każdej zmianie katalogu**, więc zapis
     * musi być tani i nie ma prawa rzucić — chodzenie po drzewie nie może się
     * zatrzymać dlatego, że katalog domowy stał się niezapisywalny.
     */
    public function rememberDirectory(string $entryId, string $path): void;

    /** Gdzie leży dokument stanu — do pokazania użytkownikowi. */
    public function location(): string;
}
