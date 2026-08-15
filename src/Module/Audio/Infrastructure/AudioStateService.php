<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Infrastructure;

use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Audio\Application\EffectAssignment;
use LightManager\Module\Audio\Application\EffectMap;
use LightManager\Module\Audio\Application\LoadedPlaylist;
use LightManager\Module\Audio\Application\Playlist;
use LightManager\Module\Audio\Application\PlaylistEntry;
use LightManager\Module\Audio\Application\Port\EffectMapPort;
use LightManager\Module\Audio\Application\Port\PlaylistPort;

/**
 * Stan modułu dźwięku w pliku `~/.light-manager/audio.json` (krok 45).
 *
 * **Plik stanu modułu, nie plik playlisty** — i to jest rozstrzygnięcie ze startu
 * kroku (D82 nr 3): krok 46 dołoży mapę hooków **kluczem**, a nie drugim plikiem,
 * więc dokument ma od pierwszego dnia kształt, który to uniesie. Klucze, których
 * ta wersja nie zna, przeżywają zapis nietknięte — inaczej starszy zapis kasowałby
 * to, co dopisała nowsza część modułu.
 *
 * Droga zapisu ta sama, co w historii komend i w konfiguracji: plik tymczasowy
 * i `rename()` w tym samym katalogu, więc przerwany zapis zostawia poprzednią,
 * poprawną wersję zamiast obciętej.
 *
 * **Żadna ścieżka nie rzuca** (zasada portu). Plik ruszony ręcznie daje pustą
 * playlistę wraz z powodem do pokazania w pasku stanu, a nieudany zapis ginie po
 * cichu — aplikacja działa wtedy tak, jak działała przed tym krokiem.
 */
final class AudioStateService extends AbstractSingleton implements PlaylistPort, EffectMapPort
{
    private const DIRECTORY = '.light-manager';

    private const FILE = 'audio.json';

    private const TEMPORARY_PREFIX = '.audio-';

    /** Klucz playlisty w dokumencie; obok niego stoi mapa efektów z kroku 46. */
    private const PLAYLIST_KEY = 'playlist';

    /**
     * Klucz mapy „zdarzenie → plik" (krok 46) — **obok playlisty, w tym samym
     * dokumencie**, dokładnie tak, jak zapowiadał krok 45.
     */
    private const HOOKS_KEY = 'hooks';

    private const PATH_KEY = 'path';

    private const NAME_KEY = 'name';

    private const ENABLED_KEY = 'enabled';

    /** Właściciel czyta i pisze, reszta świata nic — wpisy są ścieżkami. */
    private const FILE_MODE = 0o600;

    private const DIRECTORY_MODE = 0o700;

    /**
     * Ostatnio wczytany dokument — po to, żeby zapis nie skasował kluczy, których
     * ta wersja nie zna.
     *
     * @var array<string, mixed>
     */
    private array $document = [];

    /**
     * Czy dokument zdążył się przeczytać.
     *
     * Odczyt jest **jeden na proces** i to jest warunek, bez którego mapa efektów
     * z kroku 46 nie mogłaby zamieszkać w tym samym pliku: playlista i mapa
     * czytają się niezależnie od siebie, w kolejności, której nikt nie ustala,
     * a zapis jednej nie ma prawa skasować drugiej.
     */
    private bool $documentRead = false;

    public function load(): LoadedPlaylist
    {
        if (!is_file($this->location())) {
            return new LoadedPlaylist(new Playlist(), null, fresh: true);
        }

        $document = $this->document();

        if ($document === null) {
            return new LoadedPlaylist(new Playlist(), 'module.audio.playlist.unreadable');
        }

        $stored = $document[self::PLAYLIST_KEY] ?? [];

        if (!is_array($stored)) {
            return new LoadedPlaylist(new Playlist(), 'module.audio.playlist.unreadable');
        }

        return new LoadedPlaylist(new Playlist(self::entriesFrom($stored)));
    }

    public function save(Playlist $playlist): void
    {
        $this->document();
        $this->document[self::PLAYLIST_KEY] = self::documentOf($playlist);
        $this->write();
    }

    /**
     * Mapa przypisań — **pusta, gdy dokumentu nie da się przeczytać**.
     *
     * Powodu nie oddaje, w odróżnieniu od playlisty, i to nie jest niedbałość:
     * o kłopocie z tym samym plikiem mówi już `load()`, a dwa zdania o jednej
     * usterce w jednym pasku stanu znaczyłyby, że drugie wypiera pierwsze.
     */
    public function loadEffects(): EffectMap
    {
        $document = $this->document();
        $stored = $document[self::HOOKS_KEY] ?? [];

        return is_array($stored) ? new EffectMap(self::assignmentsFrom($stored)) : new EffectMap();
    }

    public function saveEffects(EffectMap $effects): void
    {
        $this->document();
        $this->document[self::HOOKS_KEY] = self::documentOfEffects($effects);
        $this->write();
    }

    /**
     * Dokument z dysku, przeczytany raz; `null` znaczy „nie da się go
     * przeczytać".
     *
     * Nieudany odczyt zostawia dokument **pusty, ale przeczytany**: kolejne
     * pytanie nie dotyka wtedy dysku, a pierwszy zapis nadpisze plik, którego
     * i tak nikt nie zrozumiał.
     *
     * @return array<string, mixed>|null
     */
    private function document(): ?array
    {
        if ($this->documentRead) {
            return $this->document;
        }

        $this->documentRead = true;
        $path = $this->location();

        if (!is_file($path)) {
            return $this->document;
        }

        $raw = @file_get_contents($path);
        /** @var mixed $decoded */
        $decoded = $raw === false ? null : json_decode($raw, true);

        if (!is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $this->document = $decoded;
    }

    /** Zapis dokumentu: plik tymczasowy i `rename()`, więc przerwany nie obcina. */
    private function write(): void
    {
        $directory = $this->directory();

        if (!is_dir($directory) && !@mkdir($directory, self::DIRECTORY_MODE, true) && !is_dir($directory)) {
            return;
        }

        $content = json_encode($this->document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($content === false) {
            return;
        }

        $temporary = $directory . DIRECTORY_SEPARATOR . self::TEMPORARY_PREFIX . getmypid() . '.tmp';

        if (@file_put_contents($temporary, $content . "\n") === false) {
            return;
        }

        @chmod($temporary, self::FILE_MODE);

        if (!@rename($temporary, $this->location())) {
            @unlink($temporary);
        }
    }

    public function location(): string
    {
        return $this->directory() . DIRECTORY_SEPARATOR . self::FILE;
    }

    /**
     * Pozycje z tego, co stało w pliku — **wpis nie do odczytania wypada, a plik
     * zostaje**.
     *
     * To jest inna reguła niż dla całego dokumentu i różnica jest celowa:
     * dokument bez sensu znaczy „nie wiem, co tu jest” i kończy się komunikatem,
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
     * Przypisania z dokumentu — **wiersz bez ścieżki wypada, reszta zostaje**, tą
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

    /** Katalog domowy z `HOME`, a w jego braku — katalog roboczy (jak w konfiguracji). */
    private function directory(): string
    {
        $home = getenv('HOME');

        if (!is_string($home) || $home === '') {
            $working = getcwd();
            $home = $working === false ? '.' : $working;
        }

        return rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DIRECTORY;
    }
}
