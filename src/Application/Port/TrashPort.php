<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

/**
 * Kosz: druga droga usunięcia i jedyna z drogą powrotną (krok 44, D81).
 *
 * Port leży w rdzeniu z tego samego powodu, co dwa poprzednie porty zapisu
 * (D66, jawny wyjątek od reguły 15): przeniesienie do kosza **zmienia dysk**,
 * a powtórzony kod piszący po dysku kosztuje utratę danych w dwóch miejscach
 * zamiast w jednym. Granica wiedzy rdzenia jest ta sama — ścieżka bezwzględna
 * jako napis, nazwa jako napis — i szersza być nie ma prawa.
 *
 * Kosz jest **katalogiem wskazywanym przy każdym wywołaniu**, a nie stanem
 * usługi (D81, nr 3): jego miejsce to pozycja ustawień modułu, a rdzeń ustawień
 * modułu nie czyta. Układ wewnątrz jest za to wszędzie ten sam — freedesktop.org:
 * wpis ląduje w `files/`, a obok, w `info/`, staje `nazwa.trashinfo` ze ścieżką
 * powrotną i datą usunięcia. **Plik informacyjny powstaje przed przeniesieniem**
 * — wpis w koszu bez niego jest wpisem, którego nie da się przywrócić — i to on
 * jest rezerwacją nazwy: kolizję rozwiązuje sufiks liczbowy (`raport.pdf`,
 * `raport.1.pdf`), jak w koszu środowiska graficznego (D81, nr 11).
 *
 * Do kosza przenosi się **zmianą nazwy, nigdy kopiowaniem** (D81, nr 4) — stąd
 * pytanie `accepts()`: wpis z innego systemu plików tą drogą nie przejdzie,
 * a co wtedy, rozstrzyga wołający pytaniem do użytkownika (D81, nr 5). Droga
 * przez kopiowanie idzie wtedy portem `FileTransferPort`, a ten port daje jej
 * wyłącznie rezerwację nazwy (`reserve()`) i sprzątanie po odwrocie
 * (`releaseUnused()`).
 */
interface TrashPort
{
    /**
     * Kosz środowiska graficznego: `$XDG_DATA_HOME/Trash`, a gdy zmiennej nie
     * ma — `~/.local/share/Trash`. Wartość domyślna pozycji ustawień.
     */
    public function defaultDirectory(): string;

    /**
     * Czy wpis wejdzie do kosza zmianą nazwy — czyli czy leży na tym samym
     * systemie plików, co kosz. Rozpoznanie idzie przez numer urządzenia, jak
     * w `FileTransferPort` (krok 42): próba i odczytanie błędu odpada, bo PHP
     * potrafi obsłużyć `EXDEV` kopiowaniem w środku wywołania.
     */
    public function accepts(string $path, string $trashDirectory): bool;

    /**
     * Przenosi wpis do kosza i oddaje nazwę, pod którą stanął w `files/`.
     *
     * @throws \LightManager\Domain\Exception\FileOperationException
     */
    public function moveToTrash(string $path, string $trashDirectory): string;

    /**
     * Rezerwuje nazwę dla wpisu, który do kosza pojedzie **kopiowaniem**
     * (inny system plików): pisze plik informacyjny i oddaje nazwę wolną
     * w `files/`. Samego wpisu nie dotyka.
     *
     * @throws \LightManager\Domain\Exception\FileOperationException
     */
    public function reserve(string $path, string $trashDirectory): string;

    /**
     * Sprząta rezerwacje, do których wpis nigdy nie dojechał: usuwa plik
     * informacyjny każdej z podanych nazw, o ile w `files/` nic pod nią nie
     * stoi. Wolno wołać zawsze — nazwa z wpisem zostaje nietknięta.
     *
     * Oddaje nazwy, które **zostały** — czyli te z wpisem w `files/`. Praca
     * przerwana w połowie pyta dokładnie o to: co naprawdę dojechało do kosza
     * i ma prawo stanąć w zapisie cofnięcia.
     *
     * @param list<string> $names nazwy w koszu, oddane przez `reserve()`
     *
     * @return list<string> nazwy z wpisem w `files/`, w kolejności podanej
     */
    public function releaseUnused(array $names, string $trashDirectory): array;

    /**
     * Przywraca wpis z kosza na ścieżkę zapisaną w pliku informacyjnym
     * i oddaje tę ścieżkę. Zajęte miejsce docelowe jest odmową, nie
     * nadpisaniem.
     *
     * @throws \LightManager\Domain\Exception\FileOperationException
     */
    public function restore(string $trashName, string $trashDirectory): string;
}
