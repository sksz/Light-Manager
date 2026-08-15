<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application\Port;

use LightManager\Module\Audio\Application\LoadedPlaylist;
use LightManager\Module\Audio\Application\Playlist;

/**
 * Trwałość playlisty — **plik stanu modułu**, nie klucz w konfiguracji
 * (krok 45, D82 rozstrzygnięcie 3).
 *
 * Powód jest twardy: `Settings::$modules` bierze wyłącznie skalary
 * (`bool|int|string`), więc lista pozycji nie zmieści się tam żadnym sposobem,
 * który nie byłby sklejaniem napisu. Nośnikiem jest własny plik modułu wzorem
 * `~/.light-manager/history` (krok 19), z tą różnicą, że trzyma **stan całego
 * modułu**, a nie samą playlistę: krok 46 dołoży do niego mapę hooków
 * **kluczem, nie drugim plikiem**.
 *
 * Żadna metoda nie rzuca — zasada portu ta sama, co w `AudioPort`
 * i `CommandHistoryPort`. Plik nieczytelny albo ruszony ręcznie daje **pustą
 * playlistę wraz z powodem**, a nieudany zapis ginie po cichu: aplikacja działa
 * bez playlisty tak samo, jak działała przed tym krokiem.
 */
interface PlaylistPort
{
    public function load(): LoadedPlaylist;

    public function save(Playlist $playlist): void;

    /** Położenie pliku — do pokazania w oknie modułu i w komunikacie o kłopocie. */
    public function location(): string;
}
