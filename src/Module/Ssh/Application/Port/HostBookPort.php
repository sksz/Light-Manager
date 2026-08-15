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
 */
interface HostBookPort
{
    public function load(): LoadedHostBook;

    public function save(HostBook $book): void;

    /** Gdzie ten plik leży — do pokazania w górnym pasie ekranu. */
    public function location(): string;
}
