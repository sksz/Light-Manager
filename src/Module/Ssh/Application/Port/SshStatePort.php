<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application\Port;

use LightManager\Module\Ssh\Domain\ValueObject\HostCredentials;

/**
 * Stan modułu sesji zdalnej — sekcja `ssh` dokumentu stanu (kroki 48–50; kształt
 * z kroku 60).
 *
 * Do kroku 60 port nazywał się „książką hostów" i niósł trzy rzeczy naraz. Adres
 * z niego wyszedł do książki adresowej, a zostały **dwie własne**: czym moduł
 * przedstawia się hostowi i gdzie ostatnio na nim stał. Obie kluczowane
 * **identyfikatorem wpisu książki**, bo to on jest tożsamością miejsca.
 *
 * Każda metoda bierze przy tym **także nazwę wpisu** i to nie jest zapas na
 * przyszłość: dane sprzed kroku 60 były kluczowane nazwą, a migracja książki nie
 * ma jak przenieść ich pod nowe identyfikatory (identyfikator powstaje losowo
 * w chwili migracji). Nazwa jest więc **drogą awaryjną odczytu** — zapis idzie
 * już wyłącznie pod identyfikator.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu): nieudany zapis ginie po cichu,
 * bo chodzenie po katalogu nie może się zatrzymać dlatego, że katalog domowy
 * stał się niezapisywalny.
 */
interface SshStatePort
{
    /** Czym przedstawić się temu wpisowi; wartości domyślne, gdy nic nie zapisano. */
    public function credentials(string $entryId, string $entryName = ''): HostCredentials;

    public function saveCredentials(string $entryId, HostCredentials $credentials): void;

    /**
     * Katalog, na którym skończyło się poprzednie oglądanie tego hosta; `null` —
     * hosta jeszcze nie otwierano albo zapis nie przetrwał.
     */
    public function lastDirectory(string $entryId, string $entryName = ''): ?string;

    /**
     * Zapamiętuje katalog. Wołane **przy każdej zmianie katalogu**, więc zapis
     * musi być tani i nie ma prawa rzucić.
     */
    public function rememberDirectory(string $entryId, string $path): void;

    /** Gdzie ten dokument leży — do pokazania w górnym pasie ekranu. */
    public function location(): string;
}
