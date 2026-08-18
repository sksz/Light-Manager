<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

/**
 * Dokument stanu aplikacji, dzielony na sekcje (krok 59, D103).
 *
 * Jeden plik (`~/.light-manager/state.json`), po jednej sekcji na właściciela —
 * to jest druga połowa rozstrzygnięcia „wspólna książka w rdzeniu": pojęcie
 * (porządek i tożsamość wpisów) niesie `Application\State\Book`, a miejsce
 * na dysku niesie ten port. Sekcja jest dla rdzenia nieprzezroczysta tak samo,
 * jak ładunek wpisu — właściciel wkłada i wyjmuje tablicę, a rdzeń pilnuje
 * wyłącznie tego, żeby **cudze sekcje i nieznane klucze przeżyły zapis**.
 *
 * **Migracja ze starych plików modułów mieszka za tym portem**, nie
 * u właścicieli: do kroku 59 sekcje leżały w osobnych plikach
 * (`audio.json`, `ssh.json`, `docker.json`) o dokładnie tej treści, którą
 * dziś niesie sekcja — więc sekcja nieobecna w dokumencie czyta się ze starego
 * pliku o nazwie sekcji. Stary plik zostaje na dysku nietknięty (nikt go już
 * nie czyta, a jego skasowanie nie ma odbiorcy); sekcją w dokumencie staje się
 * przy pierwszym zapisie któregokolwiek właściciela.
 *
 * Właściciel sięga wyłącznie po sekcję o własnym identyfikatorze — po cudzą
 * daną drogą jest rejestr kwerend (reguła 11w), nie wspólny plik.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu, krok 14): treść nie do
 * przeczytania wraca jako `null`, a nieudany zapis ginie po cichu — dokładnie
 * tak, jak robiły to trzy usługi stanu modułów, które ten port zastępuje.
 */
interface StateDocumentPort
{
    /**
     * Zawartość sekcji.
     *
     * `null` znaczy „treść jest, ale nie ma w niej sensu" — dokument stanu albo
     * stary plik modułu nie dają się rozczytać; właściciel mówi wtedy
     * użytkownikowi własnym kluczem katalogu, bo tylko on wie, czego użytkownik
     * nie zobaczy. Sekcja, której nigdzie nie ma, jest pustą tablicą — to stan
     * świeży, nie usterka.
     *
     * @return array<string, mixed>|null
     */
    public function section(string $name): ?array;

    /**
     * Czy sekcja ma już jakąkolwiek treść — w dokumencie albo w starym pliku
     * modułu.
     *
     * Rozróżnienie od sekcji pustej jest potrzebne właścicielom do migracji
     * własnych danych spoza plików stanu (np. pozycji ustawień): sekcja świeża
     * znaczy „pierwsze uruchomienie, wolno zasiać".
     */
    public function hasSection(string $name): bool;

    /** @param array<string, mixed> $data */
    public function saveSection(string $name, array $data): void;

    /** Gdzie dokument leży — do pokazania użytkownikowi. */
    public function location(): string;
}
