<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Audio\Application\LoadedPlaylist;
use LightManager\Module\Audio\Application\Playlist;
use LightManager\Module\Audio\Application\PlaylistEntry;
use LightManager\Module\Audio\Application\Port\PlaylistPort;

/**
 * Playlista w pamięci — nośnik dla testów, które nie mają prawa dotknąć pliku
 * w katalogu domowym (krok 45).
 *
 * Ta sama zasada, co przy `InMemorySettings`: test sprawdzający, **co** moduł
 * zapisuje, nie musi sprawdzać, **czym** — od tego jest osobny test usługi.
 * Zapisy zostają w `$saved`, żeby dało się sprawdzić, że playlista naprawdę
 * poszła na dysk, i ile razy.
 */
final class StubPlaylistStorage implements PlaylistPort
{
    /** @var list<list<string>> ścieżki z każdego zapisu, w kolejności */
    public array $saved = [];

    /**
     * Ile razy ktoś poprosił o wczytanie.
     *
     * Licznik jest tu dla jednego testu i jest to test warty osobnego pola:
     * uruchomienie **bez autostartu** nie ma prawa dotknąć pliku playlisty, a to
     * da się sprawdzić wyłącznie od strony nośnika.
     */
    public int $loads = 0;

    /** @param list<PlaylistEntry> $entries */
    public function __construct(
        private readonly array $entries = [],
        private readonly ?string $problemKey = null,
        private readonly bool $fresh = false,
    ) {
    }

    public function load(): LoadedPlaylist
    {
        ++$this->loads;

        return new LoadedPlaylist(new Playlist($this->entries), $this->problemKey, $this->fresh);
    }

    public function save(Playlist $playlist): void
    {
        $paths = [];

        foreach ($playlist->entries() as $entry) {
            $paths[] = $entry->path;
        }

        $this->saved[] = $paths;
    }

    public function location(): string
    {
        return '/pamięć/audio.json';
    }
}
