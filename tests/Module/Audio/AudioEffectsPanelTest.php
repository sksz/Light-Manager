<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Audio;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Event\EventRegistry;
use LightManager\Application\Module\ContextEntryKind;
use LightManager\Application\Module\ModuleContext;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\EffectAssignment;
use LightManager\Module\Audio\Application\PlaylistEntry;
use LightManager\Module\Audio\Application\PlaylistPlayer;
use LightManager\Module\Audio\Application\SoundEffects;
use LightManager\Module\Audio\Presentation\AudioQueries;
use LightManager\Module\Audio\Presentation\AudioScreen;
use LightManager\Module\Audio\Presentation\Query\EffectsQuery;
use LightManager\Module\Audio\Presentation\Query\NowPlayingQuery;
use LightManager\Module\Audio\Presentation\Query\PlaylistQuery;
use LightManager\Presentation\Cli\Query\CoreReader;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubAudio;
use LightManager\Tests\Support\StubEffectStorage;
use LightManager\Tests\Support\StubPlaylistStorage;
use LightManager\Tests\Support\StubTrackFiles;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Lewy panel okna dźwięku: spis zdarzeń i klawisze, którymi przypisuje się do
 * nich pliki (krok 46).
 *
 * Test patrzy na **klatkę**, jak test playlisty z kroku 45, bo grozi tu ten sam
 * rozjazd: przypisanie zapisane w mapie, ale nie narysowane, albo wiersz
 * pokazany dla zdarzenia, którego nikt nie ogłasza.
 *
 * Okno jest tu **szerokie** (100 kolumn), bo poniżej progu podziału widać jeden
 * panel — i to jest osobne zachowanie, sprawdzone niżej.
 */
final class AudioEffectsPanelTest extends TestCase
{
    private const COLUMNS = 100;

    private const ROWS = 12;

    private const SOUND = 'assets/sfx/fail.mp3';

    private StubAudio $audio;

    private StubEffectStorage $storage;

    private StubTrackFiles $files;

    protected function setUp(): void
    {
        $this->audio = new StubAudio();
        $this->storage = new StubEffectStorage();
        $this->files = new StubTrackFiles();
    }

    /**
     * Spis składa się ze **słownika**, a nie z mapy: widać wszystkie zdarzenia,
     * także te bez przypisania.
     */
    public function testTheListShowsEveryEventEvenWithoutAnAssignment(): void
    {
        $screen = $this->screen();
        $screen->handle(KeyPress::special(Key::Tab, "\t"));
        $texts = implode(' ', $this->texts($screen));

        self::assertStringContainsString('event.core.message.info', $texts);
        self::assertStringContainsString('event.core.command.executed', $texts);
        // Wiersz, przy którym nic nie zagra, jest **wyszarzony** — i to jest
        // sprawdzenie mocniejsze od znacznika, bo dotyczy tego, co widać
        // niezależnie od brzmienia napisu. Pytamy o wiersz **spod kursora**:
        // zaznaczony ma rolę zaznaczenia i o przypisaniu nie mówi nic.
        self::assertSame(Role::Muted, $this->roleOf($screen, 'event.core.message.warning'));
    }

    /** `Tab` przenosi ognisko między panelami — i to widać w stopce. */
    public function testTabMovesTheFocusBetweenPanels(): void
    {
        $screen = $this->screen();

        self::assertSame('module.audio.focus.playlist', $screen->focus()->labelKey);

        $screen->handle(KeyPress::special(Key::Tab, "\t"));

        self::assertSame('module.audio.focus.effects', $screen->focus()->labelKey);
    }

    /** `F7` przypisuje wpisaną ścieżkę zdarzeniu pod kursorem — i zapisuje mapę. */
    public function testTypingAPathAssignsItToTheEventUnderTheCursor(): void
    {
        $screen = $this->screen();
        $screen->handle(KeyPress::special(Key::Tab, "\t"));
        $screen->handle(KeyPress::special(Key::ArrowDown, "\e[B"));
        $screen->handle(KeyPress::special(Key::F7, "\e[18~"));

        foreach (str_split(self::SOUND) as $character) {
            $screen->handle(KeyPress::character($character));
        }

        $screen->handle(KeyPress::special(Key::Enter, "\r"));

        self::assertSame([['core.message.warning' => self::SOUND]], $this->storage->saved);
        self::assertStringContainsString('fail.mp3', implode(' ', $this->texts($screen)));
    }

    /** `F5` bierze wpis zaznaczony w przeglądarce — tą samą drogą, co playlista. */
    public function testTakingTheBrowserSelectionAssignsIt(): void
    {
        $screen = $this->screen();
        $screen->useContext(new ModuleContext('/home', 'klik.wav', ContextEntryKind::File));
        $screen->handle(KeyPress::special(Key::Tab, "\t"));
        $screen->handle(KeyPress::special(Key::F5, "\e[15~"));

        self::assertSame([['core.message.info' => '/home/klik.wav']], $this->storage->saved);
    }

    /**
     * Spacja wycisza, `F8` zabiera plik — dwa klawisze, bo to dwie różne
     * czynności.
     */
    public function testSpaceMutesAndF8TakesTheFileAway(): void
    {
        $screen = $this->screen(['core.message.info' => new EffectAssignment(self::SOUND)]);
        $screen->handle(KeyPress::special(Key::Tab, "\t"));
        // Kursor schodzi wiersz niżej, żeby pytać o rolę **przypisania**, a nie
        // o rolę zaznaczenia.
        $screen->handle(KeyPress::special(Key::ArrowDown, "\e[B"));

        self::assertSame(Role::Text, $this->roleOf($screen, 'event.core.message.info'), 'zanim wyciszono');

        $screen->handle(KeyPress::special(Key::ArrowUp, "\e[A"));
        $screen->handle(KeyPress::character(' '));
        $screen->handle(KeyPress::special(Key::ArrowDown, "\e[B"));

        self::assertSame(Role::Muted, $this->roleOf($screen, 'event.core.message.info'), 'po wyciszeniu');

        $screen->handle(KeyPress::special(Key::ArrowUp, "\e[A"));
        $screen->handle(KeyPress::special(Key::F8, "\e[19~"));

        // Dwa zapisy i różnica między nimi jest całą treścią tego testu:
        // wyciszenie zostawia ścieżkę w mapie, zabranie pliku ją stamtąd usuwa.
        self::assertSame(
            [['core.message.info' => self::SOUND], []],
            $this->storage->saved,
        );
    }

    /**
     * W oknie szerokim widać **oba** panele; w wąskim — ten z ogniskiem.
     *
     * Różnica wobec przeglądarki jest tu celowa i sprawdza ją druga połowa testu:
     * tam poniżej progu ognisko wraca na pierwszy panel, tu zostaje tam, gdzie
     * było, bo panele są dwiema różnymi rzeczami.
     */
    public function testTheNarrowWindowShowsThePanelWithTheFocus(): void
    {
        $screen = $this->screen();
        $screen->handle(KeyPress::special(Key::Tab, "\t"));

        $wide = implode(' ', $this->texts($screen));

        self::assertStringContainsString('event.core.message.info', $wide);
        self::assertStringContainsString('pierwszy', $wide, 'playlista widoczna obok');

        $narrow = implode(' ', self::textsOf($screen->draw(new Rect(0, 0, self::ROWS, 60))));

        self::assertStringContainsString('event.core.message.info', $narrow);
        self::assertStringNotContainsString('pierwszy', $narrow, 'w wąskim oknie widać panel z ogniskiem');
    }

    /** Oprawa dwóch paneli należy do ekranu — ale wyłącznie wtedy, gdy podział jest. */
    public function testTheScreenFramesBothPanelsOnlyWhenTheSplitFits(): void
    {
        $screen = $this->screen();

        self::assertNotSame([], $screen->ownFrame(new Rect(0, 0, self::ROWS, self::COLUMNS)));
        self::assertSame([], $screen->ownFrame(new Rect(0, 0, self::ROWS, 60)));
    }

    /** @param array<string, EffectAssignment> $assignments */
    private function screen(array $assignments = []): AudioScreen
    {
        $this->storage = new StubEffectStorage($assignments);
        $settings = new InMemorySettings(new Settings());

        $player = new PlaylistPlayer(
            $this->audio,
            new StubPlaylistStorage([PlaylistEntry::of('/muzyka/pierwszy.mp3')]),
            $this->files,
            $settings,
            new StubTranslator(),
        );
        $effects = new SoundEffects($this->audio, $this->storage, $this->files, $settings);

        return new AudioScreen(
            $player,
            $effects,
            new AudioQueries(self::registryOf($player, $effects)),
            new EventRegistry(),
            new StubTranslator(),
            // Odczyt ustawień rdzenia — od kroku 55 potrzebny ekranowi na
            // proporcję podziału, którą przeciąga się myszą. Rejestr bez
            // kwerend rdzenia oddaje ustawienia domyślne, i to jest poprawna
            // odpowiedź, a nie awaria (`CoreReader`).
            new CoreReader(new QueryRegistry()),
        );
    }

    /** @return list<string> */
    private function texts(AudioScreen $screen): array
    {
        return self::textsOf($screen->draw(new Rect(0, 0, self::ROWS, self::COLUMNS)));
    }

    /**
     * Rola, którą narysowano komórkę z podanym napisem — czyli to, co użytkownik
     * widzi jako „zagra" albo „nie zagra".
     */
    private function roleOf(AudioScreen $screen, string $needle): ?Role
    {
        foreach ($screen->draw(new Rect(0, 0, self::ROWS, self::COLUMNS)) as $primitive) {
            if ($primitive instanceof TextRun && str_contains($primitive->text, $needle)) {
                return $primitive->role;
            }
        }

        return null;
    }

    /**
     * @param list<Primitive> $primitives
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

    /** Rejestr z kwerendami modułu — jedyna droga odczytu także w teście (krok 53). */
    private static function registryOf(PlaylistPlayer $player, SoundEffects $effects): QueryRegistry
    {
        $registry = new QueryRegistry();
        $registry->add(AudioSettings::ID, [
            new PlaylistQuery($player),
            new NowPlayingQuery($player),
            new EffectsQuery($effects),
        ]);

        return $registry;
    }
}
