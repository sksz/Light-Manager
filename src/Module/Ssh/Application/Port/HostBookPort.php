<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application\Port;

use LightManager\Module\Ssh\Application\HostBook;

/**
 * Książka hostów na dysku (krok 48).
 *
 * Nośnikiem jest **plik stanu modułu** `~/.light-manager/ssh.json`, wzorem
 * `audio.json` z kroku 45 i z tego samego powodu (D82 nr 3): `ModuleSetting`
 * bierze wyłącznie skalary (`bool|int|string`), a profil hosta ma siedem pól.
 *
 * **Nieznane klucze przeżywają zapis od pierwszego dnia** — kroki 49 i 50 dopiszą
 * do tego samego dokumentu ostatni katalog zdalny i historię przesyłów, więc
 * schemat ma to unieść bez migracji. To nie jest zapas na przyszłość, tylko
 * uniknięcie sytuacji, w której starszy zapis kasuje to, co dopisała nowsza
 * część modułu.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu): plik ruszony ręcznie daje pustą
 * książkę wraz z powodem do pokazania, a nieudany zapis ginie po cichu.
 *
 * **Krok 49 dopisał zapowiedziane** — ostatni katalog zdalny, i to jest chwila,
 * w której zapowiedź z kroku 48 się rozliczyła: dokument uniósł nowy klucz bez
 * migracji, a plik zapisany starszą wersją modułu nie stracił niczego. Port
 * nazywa się nadal „książką hostów”, choć niesie już dwie rzeczy, i **nie jest
 * to przeoczenie**: obie są stanem tego samego modułu w tym samym dokumencie,
 * a drugi port oznaczałby dwa niezależne zapisy tego samego pliku, czyli
 * wyścig przy pierwszym zapisie z dwóch miejsc naraz.
 */
interface HostBookPort
{
    public function load(): LoadedHostBook;

    public function save(HostBook $book): void;

    /** Gdzie ten plik leży — do pokazania w górnym pasie ekranu. */
    public function location(): string;

    /**
     * Katalog, na którym skończyło się poprzednie oglądanie tego hosta; `null` —
     * hosta jeszcze nie otwierano albo zapis nie przetrwał.
     *
     * Klucz jest **nazwą wpisu książki**, bo to ona jest tożsamością hosta
     * (krok 48): dwa wpisy o tym samym adresie i różnych loginach są dwoma
     * miejscami i mają prawo pamiętać różne katalogi.
     */
    public function lastDirectory(string $hostName): ?string;

    /**
     * Zapamiętuje katalog. Wołane **przy każdej zmianie katalogu**, więc zapis
     * musi być tani i nie ma prawa rzucić — chodzenie po drzewie nie może się
     * zatrzymać dlatego, że katalog domowy stał się niezapisywalny.
     */
    public function rememberDirectory(string $hostName, string $path): void;
}
