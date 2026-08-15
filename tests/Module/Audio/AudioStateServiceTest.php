<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Audio;

use LightManager\Module\Audio\Application\EffectMap;
use LightManager\Module\Audio\Application\Playlist;
use LightManager\Module\Audio\Application\PlaylistEntry;
use LightManager\Module\Audio\Infrastructure\AudioStateService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Plik stanu modułu `~/.light-manager/audio.json` (krok 45).
 *
 * Test podstawia `HOME` na katalog tymczasowy — tą samą drogą, którą testy
 * konfiguracji i historii komend trzymają się z dala od katalogu domowego
 * użytkownika. Sprawdza przy tym rzecz, której nie widać z kodu wołającego:
 * **plik ruszony ręcznie nie wywraca startu**, a klucze, których ta wersja nie
 * zna, przeżywają zapis (krok 46 dopisze do nich mapę hooków).
 */
final class AudioStateServiceTest extends TestCase
{
    use ResetsSingletons;

    private string $home = '';

    private string|false $previousHome = false;

    protected function setUp(): void
    {
        $this->previousHome = getenv('HOME');
        $this->home = sys_get_temp_dir() . '/lm-audio-' . getmypid() . '-' . random_int(1000, 9999);

        mkdir($this->home, 0o700, true);
        putenv('HOME=' . $this->home);

        $this->resetSingleton(AudioStateService::class);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->home . '/.light-manager/*') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->home . '/.light-manager')) {
            rmdir($this->home . '/.light-manager');
        }

        rmdir($this->home);
        putenv($this->previousHome === false ? 'HOME' : 'HOME=' . $this->previousHome);

        $this->resetSingleton(AudioStateService::class);
    }

    /** Brak pliku to **zwykły stan**, nie kłopot — i tylko wtedy migracja ma prawo zadziałać. */
    public function testAMissingFileIsAFreshStartWithoutAProblem(): void
    {
        $loaded = AudioStateService::getInstance()->load();

        self::assertTrue($loaded->playlist->isEmpty());
        self::assertNull($loaded->problemKey);
        self::assertTrue($loaded->fresh);
    }

    /** Zapis i odczyt zgadzają się co do ścieżek i nazw. */
    public function testWhatWasSavedComesBack(): void
    {
        $service = AudioStateService::getInstance();
        $service->save(new Playlist([
            PlaylistEntry::of('/muzyka/pierwszy.mp3'),
            new PlaylistEntry('/muzyka/drugi.mp3', 'Drugi, nazwany inaczej'),
        ]));

        $loaded = AudioStateService::getInstance()->load();
        $first = $loaded->playlist->at(0);
        $second = $loaded->playlist->at(1);

        self::assertFalse($loaded->fresh, 'plik już jest — migracja nie ma prawa się powtórzyć');
        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame('/muzyka/pierwszy.mp3', $first->path);
        self::assertSame('pierwszy', $first->name);
        self::assertSame('Drugi, nazwany inaczej', $second->name);
    }

    /** Plik ruszony ręcznie daje **pustą playlistę i powód**, a nie wyjątek. */
    public function testAHandEditedFileGivesAnEmptyPlaylistAndAReason(): void
    {
        $this->writeState('to nie jest JSON {{{');

        $loaded = AudioStateService::getInstance()->load();

        self::assertTrue($loaded->playlist->isEmpty());
        self::assertSame('module.audio.playlist.unreadable', $loaded->problemKey);
        self::assertFalse($loaded->fresh);
    }

    /**
     * Wpis bez ścieżki wypada, a **reszta playlisty zostaje** — inna reguła niż
     * dla całego dokumentu i różnica jest celowa.
     */
    public function testAnEntryWithoutAPathFallsOutAndTheRestSurvives(): void
    {
        $this->writeState('{"playlist":[{"name":"bez ścieżki"},{"path":"/muzyka/ok.mp3"},"śmieć"]}');

        $loaded = AudioStateService::getInstance()->load();

        self::assertNull($loaded->problemKey);
        self::assertSame(1, $loaded->playlist->count());
        self::assertSame('/muzyka/ok.mp3', $loaded->playlist->at(0)?->path);
    }

    /** Klucz, którego ta wersja nie zna, przeżywa zapis — na tym stoi krok 46. */
    public function testUnknownKeysSurviveTheSave(): void
    {
        $this->writeState('{"playlist":[],"hooks":{"file.deleted":"pop.wav"}}');

        $service = AudioStateService::getInstance();
        $service->load();
        $service->save(new Playlist([PlaylistEntry::of('/muzyka/nowy.mp3')]));

        /** @var array<string, mixed> $document */
        $document = json_decode((string) file_get_contents($service->location()), true);
        $playlist = $document['playlist'] ?? null;

        self::assertSame(['file.deleted' => 'pop.wav'], $document['hooks'] ?? null);
        self::assertIsArray($playlist);
        self::assertCount(1, $playlist);
    }

    /**
     * Mapa przypisań i playlista mieszkają w **jednym dokumencie** i żadna nie
     * kasuje drugiej — niezależnie od tego, która zapisze się pierwsza (krok 46).
     *
     * To jest właśnie ta rzecz, dla której krok 45 nazwał plik „stanem modułu",
     * a nie „playlistą": zapis mapy nie zna playlisty i odwrotnie, a obie części
     * czyta się i pisze niezależnie.
     */
    public function testTheEffectMapAndThePlaylistShareOneDocument(): void
    {
        $service = AudioStateService::getInstance();

        $map = new EffectMap();
        $map->assign('core.message.error', 'assets/sfx/fail.mp3');
        $map->toggle('core.message.error');
        $service->saveEffects($map);
        $service->save(new Playlist([PlaylistEntry::of('/muzyka/nowy.mp3')]));

        $this->resetSingleton(AudioStateService::class);
        $fresh = AudioStateService::getInstance();
        $assignment = $fresh->loadEffects()->at('core.message.error');

        self::assertNotNull($assignment);
        self::assertSame('assets/sfx/fail.mp3', $assignment->path);
        self::assertFalse($assignment->enabled, 'wyciszenie przeżywa zapis');
        self::assertSame(1, $fresh->load()->playlist->count(), 'playlista przeżyła zapis mapy');
    }

    /** Wpis mapy bez ścieżki wypada, a reszta zostaje — jak pozycja playlisty. */
    public function testAnAssignmentWithoutAPathFallsOutAndTheRestSurvives(): void
    {
        $this->writeState('{"hooks":{"core.message.error":{"path":"a.wav"},"core.message.info":{"enabled":true}}}');

        $map = AudioStateService::getInstance()->loadEffects();

        self::assertNotNull($map->at('core.message.error'));
        self::assertNull($map->at('core.message.info'));
    }

    /** Plik należy do właściciela i do nikogo więcej — wpisy bywają ścieżkami. */
    public function testTheFileIsPrivateToItsOwner(): void
    {
        $service = AudioStateService::getInstance();
        $service->save(new Playlist([PlaylistEntry::of('/muzyka/nowy.mp3')]));

        self::assertSame('0600', substr(sprintf('%o', fileperms($service->location())), -4));
    }

    private function writeState(string $content): void
    {
        $directory = $this->home . '/.light-manager';

        if (!is_dir($directory)) {
            mkdir($directory, 0o700, true);
        }

        file_put_contents($directory . '/audio.json', $content);
    }
}
