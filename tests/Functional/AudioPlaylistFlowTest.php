<?php

declare(strict_types=1);

namespace LightManager\Tests\Functional;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\PlaybackMode;
use LightManager\Module\Audio\Application\PlaylistEntry;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Presentation\Ui\Module\ReadsContext;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\ScreenFixture;
use LightManager\Tests\Support\StubAudio;
use LightManager\Tests\Support\StubPlaylistStorage;
use LightManager\Tests\Support\StubTrackFiles;
use PHPUnit\Framework\TestCase;

/**
 * Playlista całą drogą użytkownika (krok 45).
 *
 * Przebieg sprawdza **zdanie-miarę kroku**: `Ctrl`+`A` otwiera okno z listą
 * utworów, `Enter` gra wskazany, a gdy utwór się skończy, następny rusza sam —
 * także wtedy, gdy użytkownik dawno wrócił do przeglądarki i o oknie audio
 * zapomniał.
 *
 * Klawisze idą przez `InputHandler`, bo skrót modułu jest klawiszem **globalnym**
 * i bez rdzenia w ogóle by nie zadziałał. Takt idzie przez `ModuleTicker`, czyli
 * tę samą drogę, którą w aplikacji prowadzi go `GameLoop` — sprawdzamy
 * mechanizm, a nie jego atrapę.
 *
 * Silnika audio nie ma tu ani przez chwilę: `StubAudio::finish()` udaje jedyne
 * zdarzenie, o które w tym kroku chodzi — utwór doszedł do końca sam.
 */
final class AudioPlaylistFlowTest extends TestCase
{
    private const NOW = 100.0;

    private const COLUMNS = 80;

    private const ROWS = 24;

    private ScreenFixture $app;

    private StubAudio $audio;

    private StubPlaylistStorage $playlist;

    protected function setUp(): void
    {
        $this->audio = new StubAudio();
        $this->playlist = new StubPlaylistStorage();
        $this->app = self::fixture($this->audio, $this->playlist);
    }

    /**
     * Sedno kroku, całą drogą: dopisz, zagraj, wróć do plików — a playlista gra
     * dalej sama.
     */
    public function testTheNextTrackStartsEvenAfterTheUserWentBackToTheFiles(): void
    {
        $this->openAudio();
        $this->addTrack('/muzyka/pierwszy.mp3');
        $this->addTrack('/muzyka/drugi.mp3');

        // Kursor stoi na pozycji dopiero co dopisanej — `Home` wraca na początek
        // listy, żeby zagrać od pierwszej.
        $this->press(KeyPress::special(Key::Home, "\e[H"));
        $this->press(KeyPress::special(Key::Enter, "\r"));

        self::assertSame(['/muzyka/pierwszy.mp3'], $this->pathsPlayed());

        // Wracamy do przeglądarki — od tej chwili okna audio nie widać, a to jest
        // dokładnie ten stan, w którym `NeedsTime` by nie pomógł (D71).
        $this->press(KeyPress::special(Key::Escape, "\e"));

        self::assertSame('browser', $this->app->screens->current()->id());

        $this->audio->finish();
        $this->tick();

        self::assertSame(['/muzyka/pierwszy.mp3', '/muzyka/drugi.mp3'], $this->pathsPlayed());
    }

    /** `Ctrl`+`A` otwiera okno modułu, a drugie naciśnięcie je zamyka. */
    public function testTheShortcutOpensAndClosesTheWindow(): void
    {
        $this->openAudio();

        self::assertSame('audio', $this->app->screens->current()->id());

        $this->press(KeyPress::ctrl('a'));

        self::assertSame('browser', $this->app->screens->current()->id());
    }

    /**
     * `F5` bierze wpis zaznaczony w przeglądarce — droga, dla której moduł nie
     * musi poznać cudzego modułu, tylko ścieżkę (D82 nr 2).
     */
    public function testTheEntrySelectedInTheBrowserLandsOnThePlaylist(): void
    {
        // Kontekst ogłasza przeglądarka przy rysowaniu, a rozdaje go
        // `FrameComposer` — raz na klatkę, każdemu ekranowi, który go czyta.
        // Przebieg powtarza obie te czynności, bo idzie ekranami, nie pętlą.
        $this->drawCurrent();
        $this->openAudio();

        $screen = $this->app->audioScreen;
        self::assertInstanceOf(ReadsContext::class, $screen, 'okno audio czyta kontekst sesji');
        $screen->useContext($this->app->state->context());

        $this->press(KeyPress::special(Key::F5, "\e[15~"));

        self::assertSame([['/muzyka/utwor.mp3']], $this->playlist->saved);
    }

    /** Tryb „zatrzymaj po utworze” kończy się ciszą — i to widać w górnym pasie okna. */
    public function testStoppingAfterATrackLeavesSilence(): void
    {
        $this->app = self::fixture($this->audio, $this->playlist, mode: PlaybackMode::StopAfterTrack);

        $this->openAudio();
        $this->addTrack('/muzyka/pierwszy.mp3');
        $this->addTrack('/muzyka/drugi.mp3');
        $this->press(KeyPress::special(Key::Home, "\e[H"));
        $this->press(KeyPress::special(Key::Enter, "\r"));

        $this->audio->finish();
        $this->tick();

        self::assertSame(['/muzyka/pierwszy.mp3'], $this->pathsPlayed());
        self::assertStringContainsString('module.audio.nothing', implode(' ', $this->headerTexts()));
    }

    /** Autostart gra od pierwszego taktu — bez otwierania okna i bez komendy. */
    public function testAutostartPlaysWithoutTheUserOpeningAnything(): void
    {
        $app = self::fixture(
            $audio = new StubAudio(),
            new StubPlaylistStorage([PlaylistEntry::of('/muzyka/pierwszy.mp3')]),
            autostart: true,
        );

        $app->ticker->tick($app->state, self::NOW);

        self::assertSame('/muzyka/pierwszy.mp3', $audio->played[0]['path'] ?? null);
        self::assertSame('browser', $app->screens->current()->id(), 'muzyka nie zabiera ekranu');
    }

    /** Bez autostartu takt niczego nie zaczyna — cisza jest stanem domyślnym. */
    public function testWithoutAutostartTheLoopStaysSilent(): void
    {
        $this->tick();

        self::assertSame([], $this->audio->played);
    }

    private function openAudio(): void
    {
        $this->press(KeyPress::ctrl('a'));
    }

    /** Utwór dopisany tak, jak robi to użytkownik: `F7`, ścieżka, `Enter`. */
    private function addTrack(string $path): void
    {
        $this->press(KeyPress::special(Key::F7, "\e[18~"));

        foreach (mb_str_split($path) as $character) {
            $this->press(KeyPress::character($character));
        }

        $this->press(KeyPress::special(Key::Enter, "\r"));
    }

    private function press(KeyPress $key): void
    {
        $this->app->input->handle($key, $this->app->state, self::NOW);
    }

    /** Dwa uderzenia taktu: pierwsze ustala chwilę startu, drugie rozlicza karencję. */
    private function tick(): void
    {
        $this->app->ticker->tick($this->app->state, self::NOW);
        $this->app->ticker->tick($this->app->state, self::NOW + 1.0);
    }

    /** @return list<string> */
    private function pathsPlayed(): array
    {
        return array_column($this->audio->played, 'path');
    }

    /** @return list<string> */
    private function headerTexts(): array
    {
        $zone = $this->app->screens->current()->header();

        return $zone === null ? [] : self::textsOf($zone->content->draw(new Rect(0, 0, 1, self::COLUMNS)));
    }

    private function drawCurrent(): void
    {
        $this->app->screens->current()->draw(new Rect(0, 0, self::ROWS, self::COLUMNS));
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

    private static function fixture(
        StubAudio $audio,
        StubPlaylistStorage $playlist,
        bool $autostart = false,
        PlaybackMode $mode = PlaybackMode::LoopList,
    ): ScreenFixture {
        $directories = (new InMemoryDirectoryRepository())
            ->add('/', [Entry::directory('muzyka')])
            ->add('/muzyka', [Entry::file('utwor.mp3', 4096)]);

        // Ustawienia podajemy **nośnikiem**, a nie stanem pętli: odtwarzacz pyta
        // o nie port konfiguracji, bo leży w warstwie `Application` i `LoopState`
        // widzieć nie może. W aplikacji jedno i drugie mówi to samo, bo zmiana
        // w zakładce idzie przez `save()`.
        $settings = new InMemorySettings(
            (new Settings())
                ->withModuleValue(AudioSettings::ID, AudioSettings::AUTOSTART, $autostart)
                ->withModuleValue(AudioSettings::ID, AudioSettings::MODE, $mode->value),
        );

        return new ScreenFixture(
            $directories->get(new DirectoryPath('/muzyka'), false),
            $directories,
            $settings,
            audio: $audio,
            playlist: $playlist,
            tracks: new StubTrackFiles(),
        );
    }
}
