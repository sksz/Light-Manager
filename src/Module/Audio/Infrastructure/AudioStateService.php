<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Infrastructure;

use LightManager\Application\Port\StateDocumentPort;
use LightManager\Infrastructure\Config\StateDocumentService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Audio\Application\EffectAssignment;
use LightManager\Module\Audio\Application\EffectMap;
use LightManager\Module\Audio\Application\LoadedPlaylist;
use LightManager\Module\Audio\Application\Playlist;
use LightManager\Module\Audio\Application\PlaylistEntry;
use LightManager\Module\Audio\Application\Port\EffectMapPort;
use LightManager\Module\Audio\Application\Port\PlaylistPort;

/**
 * Stan modułu dźwięku — sekcja `audio` dokumentu stanu (krok 45; od kroku 59
 * w `~/.light-manager/state.json`, D103).
 *
 * Usłudze została **treść sekcji**: playlista pod kluczem `playlist`, mapa
 * „zdarzenie → plik" pod `hooks` i zamiana wierszy na pozycje. Mechanizm —
 * plik, zapis tymczasowy z `rename()`, przetrwanie nieznanych kluczy, migracja
 * ze starego `audio.json` — mieszka od kroku 59 za rdzeniowym
 * `StateDocumentPort` (wynik przeglądu 15e).
 *
 * Odczyt sekcji jest **jeden na proces** i to jest warunek, bez którego mapa
 * efektów nie mogłaby mieszkać w tej samej sekcji, co playlista: obie czytają
 * się niezależnie, w kolejności, której nikt nie ustala, a zapis jednej nie ma
 * prawa skasować drugiej.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu). Sekcja ruszona ręcznie daje pustą
 * playlistę wraz z powodem do pokazania w pasku stanu, a nieudany zapis ginie po
 * cichu — aplikacja działa wtedy tak, jak działała przed tym krokiem.
 */
final class AudioStateService extends AbstractSingleton implements PlaylistPort, EffectMapPort
{
    private const SECTION = 'audio';

    /** Klucz playlisty w sekcji; obok niego stoi mapa efektów z kroku 46. */
    private const PLAYLIST_KEY = 'playlist';

    /**
     * Klucz mapy „zdarzenie → plik" (krok 46) — **obok playlisty, w tej samej
     * sekcji**, dokładnie tak, jak zapowiadał krok 45.
     */
    private const HOOKS_KEY = 'hooks';

    private const PATH_KEY = 'path';

    private const NAME_KEY = 'name';

    private const ENABLED_KEY = 'enabled';

    private ?StateDocumentPort $documents = null;

    /**
     * Ostatnio wczytana sekcja — po to, żeby zapis nie skasował kluczy, których
     * ta wersja nie zna.
     *
     * @var array<string, mixed>|null
     */
    private ?array $section = null;

    private bool $sectionRead = false;

    /** Podstawienie dokumentu stanu — **wyłącznie dla testów** (szew jak w `KubectlService`). */
    public function useSeam(StateDocumentPort $documents): void
    {
        $this->documents = $documents;
        $this->section = null;
        $this->sectionRead = false;
    }

    public function load(): LoadedPlaylist
    {
        $section = $this->section();

        if ($section === null) {
            return new LoadedPlaylist(new Playlist(), 'module.audio.playlist.unreadable');
        }

        if ($section === [] && !$this->documents()->hasSection(self::SECTION)) {
            return new LoadedPlaylist(new Playlist(), null, fresh: true);
        }

        $stored = $section[self::PLAYLIST_KEY] ?? [];

        if (!is_array($stored)) {
            return new LoadedPlaylist(new Playlist(), 'module.audio.playlist.unreadable');
        }

        return new LoadedPlaylist(new Playlist(self::entriesFrom($stored)));
    }

    public function save(Playlist $playlist): void
    {
        $section = $this->section() ?? [];
        $section[self::PLAYLIST_KEY] = self::documentOf($playlist);
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
    }

    /**
     * Mapa przypisań — **pusta, gdy sekcji nie da się przeczytać**.
     *
     * Powodu nie oddaje, w odróżnieniu od playlisty, i to nie jest niedbałość:
     * o kłopocie z tą samą sekcją mówi już `load()`, a dwa zdania o jednej
     * usterce w jednym pasku stanu znaczyłyby, że drugie wypiera pierwsze.
     */
    public function loadEffects(): EffectMap
    {
        $stored = ($this->section() ?? [])[self::HOOKS_KEY] ?? [];

        return is_array($stored) ? new EffectMap(self::assignmentsFrom($stored)) : new EffectMap();
    }

    public function saveEffects(EffectMap $effects): void
    {
        $section = $this->section() ?? [];
        $section[self::HOOKS_KEY] = self::documentOfEffects($effects);
        $this->section = $section;
        $this->documents()->saveSection(self::SECTION, $section);
    }

    public function location(): string
    {
        return $this->documents()->location();
    }

    /**
     * Pozycje z tego, co stało w sekcji — **wpis nie do odczytania wypada,
     * a sekcja zostaje**.
     *
     * To jest inna reguła niż dla całej sekcji i różnica jest celowa: sekcja
     * bez sensu znaczy „nie wiem, co tu jest” i kończy się komunikatem,
     * a pojedynczy wpis bez ścieżki znaczy „tej jednej pozycji nie ma” — reszta
     * playlisty jest wtedy w porządku i nie ma powodu jej tracić.
     *
     * @param array<mixed> $stored
     *
     * @return list<PlaylistEntry>
     */
    private static function entriesFrom(array $stored): array
    {
        $entries = [];

        foreach ($stored as $item) {
            if (!is_array($item)) {
                continue;
            }

            $path = $item[self::PATH_KEY] ?? null;

            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            $name = $item[self::NAME_KEY] ?? null;

            $entries[] = is_string($name) && $name !== ''
                ? new PlaylistEntry($path, $name)
                : PlaylistEntry::of($path);
        }

        return $entries;
    }

    /**
     * Przypisania z sekcji — **wiersz bez ścieżki wypada, reszta zostaje**, tą
     * samą regułą, co pozycja playlisty bez ścieżki.
     *
     * Nazw zdarzeń **nie sprawdzamy** i to jest celowe: słownik zna dopiero
     * `EventRegistry` złożony przy starcie, a przypisanie do zdarzenia z modułu
     * chwilowo wyłączonego ma przeżyć jego wyłączenie. Wiersz spoza słownika po
     * prostu nie pokaże się w oknie i nigdy nie zagra.
     *
     * @param array<mixed> $stored
     *
     * @return array<string, EffectAssignment>
     */
    private static function assignmentsFrom(array $stored): array
    {
        $assignments = [];

        foreach ($stored as $event => $item) {
            if (!is_string($event) || $event === '' || !is_array($item)) {
                continue;
            }

            $path = $item[self::PATH_KEY] ?? null;

            if (!is_string($path) || trim($path) === '') {
                continue;
            }

            $enabled = $item[self::ENABLED_KEY] ?? true;
            $assignments[$event] = new EffectAssignment($path, !is_bool($enabled) || $enabled);
        }

        return $assignments;
    }

    /** @return array<string, array{path: string, enabled: bool}> */
    private static function documentOfEffects(EffectMap $effects): array
    {
        $stored = [];

        foreach ($effects->all() as $event => $assignment) {
            // Dostępności pliku nie zapisujemy — z tego samego powodu, co przy
            // playliście: to odpowiedź na pytanie zadane przy ostatnim
            // sprawdzeniu, a nie własność przypisania.
            $stored[$event] = [self::PATH_KEY => $assignment->path, self::ENABLED_KEY => $assignment->enabled];
        }

        return $stored;
    }

    /** @return list<array<string, string>> */
    private static function documentOf(Playlist $playlist): array
    {
        $stored = [];

        foreach ($playlist->entries() as $entry) {
            // Dostępności pliku **nie zapisujemy**: to odpowiedź na pytanie
            // zadane przy ostatnim sprawdzeniu, a nie własność pozycji. Nośnik
            // odpięty wczoraj bywa dziś podpięty.
            $stored[] = [self::PATH_KEY => $entry->path, self::NAME_KEY => $entry->name];
        }

        return $stored;
    }

    /**
     * Sekcja z dokumentu stanu, przeczytana raz; `null` znaczy „nie da się jej
     * przeczytać".
     *
     * @return array<string, mixed>|null
     */
    private function section(): ?array
    {
        if (!$this->sectionRead) {
            $this->sectionRead = true;
            $this->section = $this->documents()->section(self::SECTION);
        }

        return $this->section;
    }

    private function documents(): StateDocumentPort
    {
        return $this->documents ?? StateDocumentService::getInstance();
    }
}
