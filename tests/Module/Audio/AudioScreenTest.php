<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Audio;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Module\Audio\Application\PlaylistEntry;
use LightManager\Module\Audio\Application\PlaylistPlayer;
use LightManager\Module\Audio\Application\SoundEffects;
use LightManager\Module\Audio\Presentation\AudioScreen;
use LightManager\Presentation\Ui\Transition;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubAudio;
use LightManager\Tests\Support\StubEffectStorage;
use LightManager\Tests\Support\StubPlaylistStorage;
use LightManager\Tests\Support\StubTrackFiles;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Okno modułu dźwięku: playlista, którą widać, i klawisze, które nią władają
 * (krok 45).
 *
 * Test patrzy na **klatkę**, a nie na pola ekranu, bo błąd, który tu naprawdę
 * grozi, jest rozjazdem między jednym a drugim: kursor przesunięty w polu, ale
 * nie na obrazie, albo pozycja usunięta z listy, która wciąż się rysuje.
 */
final class AudioScreenTest extends TestCase
{
    private const COLUMNS = 60;

    private const ROWS = 10;

    private StubAudio $audio;

    private StubPlaylistStorage $storage;

    private StubTrackFiles $files;

    protected function setUp(): void
    {
        $this->audio = new StubAudio();
        $this->storage = new StubPlaylistStorage([
            PlaylistEntry::of('/muzyka/pierwszy.mp3'),
            PlaylistEntry::of('/muzyka/drugi.mp3'),
            PlaylistEntry::of('/muzyka/trzeci.mp3'),
        ]);
        $this->files = new StubTrackFiles();
    }

    /** Komponentu rdzenia nie dokłada ani jednego — całość to lista i etykiety. */
    public function testDrawsThePlaylistWithTheNamesOfTheTracks(): void
    {
        $texts = $this->texts($this->screen());

        self::assertStringContainsString('pierwszy', implode(' ', $texts));
        self::assertStringContainsString('drugi', implode(' ', $texts));
    }

    /** `Enter` gra wskazany utwór, a znacznik pokazuje, który to jest (D82 nr 4). */
    public function testEnterPlaysTheSelectedTrackAndMarksIt(): void
    {
        $screen = $this->screen();
        $screen->handle(KeyPress::special(Key::ArrowDown, "\e[B"));
        $screen->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame('/muzyka/drugi.mp3', $this->audio->played[0]['path']);
        self::assertStringContainsString('▶ drugi', implode(' ', $this->texts($screen)));
    }

    /** Kursor listy i utwór grany to **dwie różne rzeczy** — jedno nie goni drugiego. */
    public function testTheListCursorIsNotThePlayingCursor(): void
    {
        $screen = $this->screen();
        $screen->handle(KeyPress::special(Key::Enter, "\r"));
        $screen->handle(KeyPress::special(Key::ArrowDown, "\e[B"));

        $frame = implode(' ', $this->texts($screen));

        self::assertStringContainsString('▶ pierwszy', $frame, 'gra dalej pierwszy');
        self::assertSame('/muzyka/pierwszy.mp3', $this->audio->played[0]['path']);
    }

    /** `Shift`+strzałka przestawia pozycję i **zabiera kursor ze sobą** (D82 nr 8). */
    public function testShiftArrowMovesThePositionAndTakesTheCursorWithIt(): void
    {
        $screen = $this->screen();
        $screen->handle(KeyPress::shifted(Key::ArrowDown, "\e[1;2B"));

        self::assertSame(
            [['/muzyka/drugi.mp3', '/muzyka/pierwszy.mp3', '/muzyka/trzeci.mp3']],
            $this->storage->saved,
            'przestawienie zapisuje się od razu',
        );

        $screen->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame('/muzyka/pierwszy.mp3', $this->audio->played[0]['path'], 'kursor poszedł za pozycją');
    }

    /** Goła strzałka nie ma prawa złapać przestawienia (reguła 11j). */
    public function testAPlainArrowDoesNotMoveThePosition(): void
    {
        $screen = $this->screen();
        $screen->handle(KeyPress::special(Key::ArrowDown, "\e[B"));

        self::assertSame([], $this->storage->saved);
    }

    /** `F8` i `Delete` usuwają pozycję — obie drogi robią to samo. */
    public function testRemovingAPositionPersistsTheShorterPlaylist(): void
    {
        $screen = $this->screen();
        $screen->handle(KeyPress::special(Key::F8, "\e[19~"));

        self::assertSame([['/muzyka/drugi.mp3', '/muzyka/trzeci.mp3']], $this->storage->saved);
        self::assertStringNotContainsString('pierwszy', implode(' ', $this->texts($screen)));
    }

    /**
     * `F5` bierze wpis zaznaczony w przeglądarce — **przez kontekst sesji**, a nie
     * przez poznanie cudzego modułu.
     */
    public function testTakingTheBrowserSelectionGoesThroughTheContext(): void
    {
        $screen = $this->screen();
        $screen->useContext(new ModuleContext('/muzyka', 'czwarty.mp3', ContextEntryKind::File));

        $outcome = $screen->handle(KeyPress::special(Key::F5, "\e[15~"));

        self::assertSame(MessageTone::Info, $outcome->message?->tone);
        self::assertSame([['/muzyka/pierwszy.mp3', '/muzyka/drugi.mp3', '/muzyka/trzeci.mp3', '/muzyka/czwarty.mp3']], $this->storage->saved);
    }

    /** Kontekst pusty kończy się zdaniem, a nie milczeniem — klawisz nie ma wyglądać na zepsuty. */
    public function testTakingNothingExplainsItself(): void
    {
        $outcome = $this->screen()->handle(KeyPress::special(Key::F5, "\e[15~"));

        self::assertSame(MessageTone::Warning, $outcome->message?->tone);
        self::assertSame([], $this->storage->saved);
    }

    /** `F7` otwiera pole na ścieżkę, a `Enter` dopisuje wpisany utwór. */
    public function testTypingAPathAddsATrack(): void
    {
        $screen = $this->screen();
        $screen->handle(KeyPress::special(Key::F7, "\e[18~"));

        foreach (['/', 'x', '.', 'm', 'p', '3'] as $character) {
            $screen->handle(KeyPress::character($character));
        }

        self::assertStringContainsString('/x.mp3', implode(' ', $this->texts($screen)), 'pole widać w klatce');

        $screen->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame('/x.mp3', $this->storage->saved[0][3] ?? null);
    }

    /** `Esc` w polu zamyka **pole**, a nie ekran — dopiero drugie naciśnięcie wraca do modułu. */
    public function testEscapeClosesTheFieldFirstAndTheScreenSecond(): void
    {
        $screen = $this->screen();
        $screen->handle(KeyPress::special(Key::F7, "\e[18~"));

        self::assertSame(Transition::Stay, $screen->handle(KeyPress::special(Key::Escape, "\e"))->transition);
        self::assertSame(Transition::Close, $screen->handle(KeyPress::special(Key::Escape, "\e"))->transition);
    }

    /** Pozycja bez pliku jest wyszarzona i podpisana powodem — a z listy nie znika. */
    public function testAMissingFileIsMarkedInsteadOfDisappearing(): void
    {
        $this->files = new StubTrackFiles(missing: ['/muzyka/drugi.mp3']);
        $screen = $this->screen();
        $screen->reset();

        self::assertStringContainsString('module.audio.playlist.missing', implode(' ', $this->texts($screen)));
    }

    /** Playlista pusta mówi, co z tym zrobić, zamiast pokazywać pusty prostokąt. */
    public function testAnEmptyPlaylistSaysWhatToDo(): void
    {
        $this->storage = new StubPlaylistStorage();

        self::assertStringContainsString('module.audio.playlist.empty', implode(' ', $this->texts($this->screen())));
    }

    /** Plik ruszony ręcznie: **powód prawdziwy** wyprzedza zdanie o pustej liście. */
    public function testABrokenPlaylistFileShowsItsRealReason(): void
    {
        $this->storage = new StubPlaylistStorage(problemKey: 'module.audio.playlist.unreadable');

        self::assertStringContainsString(
            'module.audio.playlist.unreadable',
            implode(' ', $this->texts($this->screen())),
        );
    }

    /** Górny pas mówi, co gra i w jakim trybie — dwie rzeczy, o które pyta się najczęściej. */
    public function testTheHeaderTellsWhatPlaysAndInWhichMode(): void
    {
        $screen = $this->screen();
        $header = $screen->header();
        $before = self::textsOf($header->content->draw(new Rect(0, 0, 1, self::COLUMNS)));

        self::assertStringContainsString('module.audio.nothing', implode(' ', $before));
        self::assertStringContainsString('module.audio.mode.list', implode(' ', $before));

        $screen->handle(KeyPress::special(Key::Enter, "\r"));
        $after = self::textsOf($screen->header()->content->draw(new Rect(0, 0, 1, self::COLUMNS)));

        // Atrapa tłumacza oddaje klucz wraz z parametrami, a etykieta przycina
        // wiersz do szerokości — sprawdzamy więc klucz, nie doklejoną nazwę.
        self::assertStringContainsString('module.audio.nowPlaying', implode(' ', $after));
        self::assertStringNotContainsString('module.audio.nothing', implode(' ', $after));
    }

    /** Spacja zatrzymuje i wznawia — bo silnik pauzuje, a nie przewija. */
    public function testSpacePausesAndResumes(): void
    {
        $screen = $this->screen();
        $screen->handle(KeyPress::special(Key::Enter, "\r"));
        $screen->handle(KeyPress::character(' '));

        self::assertSame(1, $this->audio->stopCount);

        $screen->handle(KeyPress::character(' '));

        self::assertCount(2, $this->audio->played, 'wznowienie gra tę samą pozycję');
        self::assertSame('/muzyka/pierwszy.mp3', $this->audio->played[1]['path']);
    }

    private function screen(): AudioScreen
    {
        $settings = new InMemorySettings(new Settings());

        return new AudioScreen(
            new PlaylistPlayer(
                $this->audio,
                $this->storage,
                $this->files,
                $settings,
                new StubTranslator(),
            ),
            new SoundEffects($this->audio, new StubEffectStorage(), $this->files, $settings),
            new EventRegistry(),
            new StubTranslator(),
        );
    }

    /** @return list<string> */
    private function texts(AudioScreen $screen): array
    {
        return self::textsOf($screen->draw(new Rect(0, 0, self::ROWS, self::COLUMNS)));
    }

    /**
     * @param list<\LightManager\Application\Ui\Primitive\Primitive> $primitives
     *
     * @return list<string>
     */
    private static function textsOf(array $primitives): array
    {
        $texts = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }
}
