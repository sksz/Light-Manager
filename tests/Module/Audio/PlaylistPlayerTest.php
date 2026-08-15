<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Audio;

use LightManager\Application\Dto\Settings;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\PlaybackMode;
use LightManager\Module\Audio\Application\PlaylistEntry;
use LightManager\Module\Audio\Application\PlaylistPlayer;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubAudio;
use LightManager\Tests\Support\StubPlaylistStorage;
use LightManager\Tests\Support\StubTrackFiles;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Playlista, która gra dalej sama — sedno kroku 45, sprawdzone **bez ani jednego
 * dźwięku**.
 *
 * Silnik zastępuje `StubAudio`, którego `finish()` udaje jedyne zdarzenie
 * istotne dla taktu: utwór doszedł do końca sam z siebie. Rozróżnienie „koniec”
 * od „pauzy” jest tu najważniejszą rzeczą do sprawdzenia, bo jego brak dawałby
 * playlistę przeskakującą utwór za każdym naciśnięciem pauzy.
 */
final class PlaylistPlayerTest extends TestCase
{
    /** Chwila „po karencji” — takt wierzy silnikowi dopiero po pół sekundy. */
    private const AFTER_GRACE = 10.0;

    private StubAudio $audio;

    private StubPlaylistStorage $storage;

    private StubTrackFiles $files;

    protected function setUp(): void
    {
        $this->audio = new StubAudio();
        $this->storage = new StubPlaylistStorage([
            PlaylistEntry::of('a.mp3'),
            PlaylistEntry::of('b.mp3'),
        ]);
        $this->files = new StubTrackFiles();
    }

    /** Sedno kroku: skończony utwór ustępuje następnemu — bez niczyjego udziału. */
    public function testTheNextTrackStartsWhenTheCurrentOneEnds(): void
    {
        $player = $this->player();
        $player->play(0);

        $this->audio->finish();
        $this->pass($player);

        self::assertSame(['a.mp3', 'b.mp3'], $this->pathsPlayed());
        self::assertSame(1, $player->playlist()->playing());
    }

    /**
     * **Pauza to nie koniec utworu.** Bez tego rozróżnienia playlista
     * przeskakiwałaby utwór za każdym naciśnięciem pauzy — a silnik odpowiada
     * w obu przypadkach „nie gram”.
     */
    public function testPausingDoesNotAdvanceThePlaylist(): void
    {
        $player = $this->player();
        $player->play(0);
        $player->pause();

        $this->pass($player);

        self::assertSame(['a.mp3'], $this->pathsPlayed());
        self::assertSame(0, $player->playlist()->playing(), 'pauza nie rusza kursora');
    }

    /**
     * Karencja po starcie: zanim minie pół sekundy, takt nie wierzy silnikowi, że
     * nic nie gra. Bez niej świeżo puszczony utwór wyglądałby na skończony
     * i playlista przelatywałaby całą listę w ułamku sekundy.
     */
    public function testTheGraceAfterStartingProtectsAFreshTrack(): void
    {
        $player = $this->player();
        $player->play(0);
        $this->audio->finish();

        $player->tick(1000.0);
        $player->tick(1000.1);

        self::assertSame(['a.mp3'], $this->pathsPlayed(), 'w karencji nic się nie zmienia');

        $player->tick(1001.0);

        self::assertSame(['a.mp3', 'b.mp3'], $this->pathsPlayed());
    }

    /** „Zatrzymaj po utworze” kończy się ciszą, a nie następną pozycją. */
    public function testStoppingAfterATrackFallsSilent(): void
    {
        $player = $this->player($this->settingsWith(AudioSettings::MODE, PlaybackMode::StopAfterTrack->value));
        $player->play(0);

        $this->audio->finish();
        $this->pass($player);

        self::assertSame(['a.mp3'], $this->pathsPlayed());
    }

    /**
     * Powtarzanie utworu zapętla **silnik**, a nie playlista: takt nie ma czego
     * robić, bo utwór się nie kończy.
     */
    public function testRepeatingATrackIsTheEnginesJob(): void
    {
        $player = $this->player($this->settingsWith(AudioSettings::MODE, PlaybackMode::RepeatTrack->value));
        $player->play(0);

        self::assertTrue($this->audio->played[0]['loop'], 'zapętlenie idzie do silnika');
    }

    /** Pozycja bez pliku jest pomijana przy przejściu dalej — i nie zatrzymuje playlisty. */
    public function testAMissingFileIsSkippedInsteadOfStoppingThePlaylist(): void
    {
        $this->storage = new StubPlaylistStorage([
            PlaylistEntry::of('a.mp3'),
            PlaylistEntry::of('znikl.mp3'),
            PlaylistEntry::of('c.mp3'),
        ]);
        $this->files = new StubTrackFiles(missing: ['znikl.mp3']);

        $player = $this->player();
        $player->play(0);
        $this->audio->finish();
        $this->pass($player);

        self::assertSame(['a.mp3', 'c.mp3'], $this->pathsPlayed());
    }

    /** Autostart gra od pierwszego taktu — i tylko wtedy, gdy użytkownik o to poprosił. */
    public function testAutostartPlaysFromTheFirstTick(): void
    {
        $player = $this->player($this->settingsWith(AudioSettings::AUTOSTART, true));
        $player->tick(1.0);

        self::assertSame(['a.mp3'], $this->pathsPlayed());
    }

    /**
     * Bez autostartu pierwszy takt **nie dotyka nawet pliku playlisty** — a to
     * jest cała cena, którą uruchomienie bez muzyki płaci za ten krok.
     */
    public function testWithoutAutostartTheFirstTickTouchesNothing(): void
    {
        $player = $this->player();

        $player->tick(1.0);
        $player->tick(2.0);

        self::assertSame(0, $this->storage->loads, 'playlista wczytuje się dopiero, gdy ktoś o nią pyta');
        self::assertSame([], $this->pathsPlayed());
    }

    /**
     * Migracja z kroku 36: utwór spod klucza `track` wchodzi na playlistę przy
     * pierwszym uruchomieniu po zmianie — i **od razu się zapisuje**, żeby nie
     * wracał po każdym opróżnieniu listy.
     */
    public function testTheTrackFromTheOldSettingSeedsAFreshPlaylist(): void
    {
        $this->storage = new StubPlaylistStorage(fresh: true);
        $settings = (new Settings())->withModuleValue(AudioSettings::ID, AudioSettings::TRACK, '/muzyka/stary.mp3');

        $player = $this->player(new InMemorySettings($settings));

        self::assertSame(['/muzyka/stary.mp3'], self::pathsOf($player));
        self::assertSame([['/muzyka/stary.mp3']], $this->storage->saved);
    }

    /** Playlista opróżniona przez użytkownika **zostaje pusta** — migracja jej nie wskrzesza. */
    public function testAnEmptiedPlaylistIsNotSeededAgain(): void
    {
        $this->storage = new StubPlaylistStorage(fresh: false);

        self::assertSame([], self::pathsOf($this->player()));
    }

    /** Plik ruszony ręcznie daje pustą playlistę **wraz z powodem**, nigdy wyjątek. */
    public function testAHandEditedFileGivesAnEmptyPlaylistAndAReason(): void
    {
        $this->storage = new StubPlaylistStorage(problemKey: 'module.audio.playlist.unreadable');

        $player = $this->player();

        self::assertTrue($player->playlist()->isEmpty());
        self::assertNotNull($player->problem());
        self::assertStringContainsString('module.audio.playlist.unreadable', (string) $player->problem());
    }

    /** Dopisanie utworu zapisuje playlistę od razu — zmiana ma przeżyć zamknięcie aplikacji. */
    public function testAddingATrackPersistsThePlaylist(): void
    {
        $player = $this->player();

        self::assertNull($player->add('/muzyka/nowy.mp3'));
        self::assertSame([['a.mp3', 'b.mp3', '/muzyka/nowy.mp3']], $this->storage->saved);
    }

    /** Ścieżka pusta nie wchodzi — i mówi dlaczego, zamiast milczeć. */
    public function testAnEmptyPathIsRefusedWithASentence(): void
    {
        $player = $this->player();

        self::assertNotNull($player->add('  '));
        self::assertSame([], $this->storage->saved);
    }

    /**
     * Nieudane granie **oznacza pozycję jako brakującą** i przerywa prowadzenie:
     * plik mógł zniknąć między jednym utworem a drugim.
     */
    public function testAFailedStartMarksTheEntryAndStopsLeading(): void
    {
        $this->audio = new StubAudio(problem: 'nie ma czego grać');
        $this->files = new StubTrackFiles(missing: ['a.mp3']);

        $player = $this->player();

        self::assertSame('nie ma czego grać', $player->play(0));
        self::assertTrue($player->playlist()->at(0)?->missing);

        $this->pass($player);

        self::assertCount(1, $this->audio->played, 'po nieudanej próbie takt nie próbuje dalej');
    }

    /** Wznowienie bez playlisty mówi zdaniem, a nie ciszą. */
    public function testResumingAnEmptyPlaylistExplainsItself(): void
    {
        $this->storage = new StubPlaylistStorage();

        self::assertNotNull($this->player()->resume());
    }

    /** Głośność bierze się z ustawień w chwili uruchomienia utworu. */
    public function testVolumeComesFromTheSettingsAtTheMomentOfPlaying(): void
    {
        $player = $this->player($this->settingsWith(AudioSettings::VOLUME, 30));
        $player->play(0);

        self::assertSame(30, $this->audio->played[0]['volume']);
    }

    /** Takt po karencji, w dwóch uderzeniach: pierwsze ustala chwilę startu, drugie rozlicza. */
    private function pass(PlaylistPlayer $player): void
    {
        $player->tick(self::AFTER_GRACE);
        $player->tick(self::AFTER_GRACE + 1.0);
    }

    private function player(?InMemorySettings $settings = null): PlaylistPlayer
    {
        return new PlaylistPlayer(
            $this->audio,
            $this->storage,
            $this->files,
            $settings ?? new InMemorySettings(new Settings()),
            new StubTranslator(),
        );
    }

    private function settingsWith(string $key, bool|int|string $value): InMemorySettings
    {
        return new InMemorySettings((new Settings())->withModuleValue(AudioSettings::ID, $key, $value));
    }

    /** @return list<string> */
    private function pathsPlayed(): array
    {
        $paths = [];

        foreach ($this->audio->played as $request) {
            $paths[] = $request['path'];
        }

        return $paths;
    }

    /** @return list<string> */
    private static function pathsOf(PlaylistPlayer $player): array
    {
        $paths = [];

        foreach ($player->playlist()->entries() as $entry) {
            $paths[] = $entry->path;
        }

        return $paths;
    }
}
