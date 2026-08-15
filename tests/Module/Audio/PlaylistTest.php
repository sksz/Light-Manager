<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Audio;

use LightManager\Module\Audio\Application\PlaybackMode;
use LightManager\Module\Audio\Application\Playlist;
use LightManager\Module\Audio\Application\PlaylistEntry;
use LightManager\Module\Audio\Domain\Exception\InvalidTrackException;
use PHPUnit\Framework\TestCase;

/**
 * Playlista jako **dane i reguły kolejności** — bez dźwięku, bez dysku i bez
 * ustawień (krok 45).
 *
 * Cała arytmetyka „co jest następne” daje się dzięki temu sprawdzić wprost,
 * a testy odtwarzacza mogą się zająć wyłącznie tym, czego ta klasa nie wie:
 * silnikiem i taktem.
 */
final class PlaylistTest extends TestCase
{
    /** Nazwa bierze się z pliku bez rozszerzenia — tak podpisuje utwory każdy odtwarzacz. */
    public function testEntryNamesItselfAfterTheFile(): void
    {
        $entry = PlaylistEntry::of('/muzyka/Deep Purple - Smoke On The Water.mp3');

        self::assertSame('Deep Purple - Smoke On The Water', $entry->name);
        self::assertFalse($entry->missing);
    }

    /** Pozycja bez ścieżki nie ma prawa powstać — obiekt wartości pilnuje się sam. */
    public function testEntryRefusesToExistWithoutAPath(): void
    {
        $this->expectException(InvalidTrackException::class);

        PlaylistEntry::of('   ');
    }

    /** Dopisanie stawia na końcu, a kursor grania zostaje tam, gdzie był. */
    public function testAddingPutsTheEntryAtTheEndAndLeavesThePlayingCursorAlone(): void
    {
        $playlist = self::of('a.mp3', 'b.mp3');
        $playlist->usePlaying(0);

        self::assertSame(2, $playlist->add(PlaylistEntry::of('c.mp3')));
        self::assertSame(0, $playlist->playing());
        self::assertSame(3, $playlist->count());
    }

    /** Usunięcie pozycji **przed** graną przesuwa kursor, żeby wskazywał ten sam utwór. */
    public function testRemovingBeforeThePlayingEntryKeepsPointingAtTheSameTrack(): void
    {
        $playlist = self::of('a.mp3', 'b.mp3', 'c.mp3');
        $playlist->usePlaying(2);

        self::assertTrue($playlist->removeAt(0));
        self::assertSame(1, $playlist->playing());
        self::assertSame('c.mp3', $playlist->at(1)?->path);
    }

    /** Usunięcie granej pozycji zostawia playlistę bez kursora — utwór gra dalej, lista o nim nie wie. */
    public function testRemovingThePlayingEntryClearsTheCursor(): void
    {
        $playlist = self::of('a.mp3', 'b.mp3');
        $playlist->usePlaying(1);

        self::assertTrue($playlist->removeAt(1));
        self::assertNull($playlist->playing());
    }

    /** Przestawienie zabiera kursor grania ze sobą, bo wskazuje utwór, a nie miejsce. */
    public function testMovingAnEntryCarriesThePlayingCursorWithIt(): void
    {
        $playlist = self::of('a.mp3', 'b.mp3', 'c.mp3');
        $playlist->usePlaying(2);

        self::assertSame(1, $playlist->swap(2, -1));
        self::assertSame(1, $playlist->playing());
        self::assertSame(['a.mp3', 'c.mp3', 'b.mp3'], self::pathsOf($playlist));
    }

    /** Przestawienie poza listę nie robi nic — także wtedy, gdy ktoś naciśnie klawisz dwadzieścia razy. */
    public function testMovingPastTheEdgeChangesNothing(): void
    {
        $playlist = self::of('a.mp3', 'b.mp3');

        self::assertSame(0, $playlist->swap(0, -1));
        self::assertSame(1, $playlist->swap(1, 1));
        self::assertSame(['a.mp3', 'b.mp3'], self::pathsOf($playlist));
    }

    /** Pętla listy idzie do przodu i zawija się na końcu. */
    public function testLoopingTheListWrapsAround(): void
    {
        $playlist = self::of('a.mp3', 'b.mp3');

        self::assertSame(1, $playlist->nextAfter(0, PlaybackMode::LoopList));
        self::assertSame(0, $playlist->nextAfter(1, PlaybackMode::LoopList));
        self::assertSame(0, $playlist->nextAfter(null, PlaybackMode::LoopList), 'brak kursora znaczy „od początku”');
    }

    /** „Zatrzymaj po utworze” nie ma następnika — i to jest cała jego treść. */
    public function testStoppingAfterATrackHasNoSuccessor(): void
    {
        $playlist = self::of('a.mp3', 'b.mp3');

        self::assertNull($playlist->nextAfter(0, PlaybackMode::StopAfterTrack));
    }

    /** Powtarzanie utworu wskazuje ten sam numer — zapętla i tak silnik. */
    public function testRepeatingATrackPointsAtItself(): void
    {
        $playlist = self::of('a.mp3', 'b.mp3');

        self::assertSame(1, $playlist->nextAfter(1, PlaybackMode::RepeatTrack));
    }

    /**
     * Pozycja bez pliku **zostaje na liście**, ale wypada z wyboru „co dalej”
     * (D82 nr 6).
     */
    public function testMissingEntriesStayOnTheListAndFallOutOfThePlayingOrder(): void
    {
        $playlist = self::of('a.mp3', 'znikl.mp3', 'c.mp3');
        $playlist->refresh(static fn (string $path): bool => $path !== 'znikl.mp3');

        self::assertSame(3, $playlist->count(), 'lista nie traci pozycji');
        self::assertTrue($playlist->at(1)?->missing);
        self::assertSame(2, $playlist->nextAfter(0, PlaybackMode::LoopList), 'brakująca jest pominięta');
        self::assertFalse($playlist->isPlayable(1));
    }

    /** Playlista złożona z samych braków nie ma czego zagrać — i mówi to `null`em. */
    public function testAPlaylistOfMissingFilesHasNothingToPlay(): void
    {
        $playlist = self::of('a.mp3', 'b.mp3');
        $playlist->refresh(static fn (string $path): bool => false);

        self::assertNull($playlist->firstPlayable());
        self::assertNull($playlist->nextAfter(0, PlaybackMode::LoopList));
    }

    /** Kursor grania spoza listy znaczy „nie gra nic” — pustki wskazywać nie wolno. */
    public function testThePlayingCursorRefusesToPointOutsideTheList(): void
    {
        $playlist = self::of('a.mp3');
        $playlist->usePlaying(7);

        self::assertNull($playlist->playing());
    }

    private static function of(string ...$paths): Playlist
    {
        $entries = [];

        foreach ($paths as $path) {
            $entries[] = PlaylistEntry::of($path);
        }

        return new Playlist($entries);
    }

    /** @return list<string> */
    private static function pathsOf(Playlist $playlist): array
    {
        $paths = [];

        foreach ($playlist->entries() as $entry) {
            $paths[] = $entry->path;
        }

        return $paths;
    }
}
