<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Audio;

use LightManager\Application\Command\CommandInput;
use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Command\CommandOutcome;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Command\CommandTransition;
use LightManager\Application\Command\SuggestsArguments;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleRegistry;
use LightManager\Application\Module\ModuleSetting;
use LightManager\Application\Module\ModuleSettingKind;
use LightManager\Application\Module\NeedsTick;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Application\PlaybackMode;
use LightManager\Module\Audio\Application\PlaylistEntry;
use LightManager\Module\Audio\Presentation\AudioModule;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubAudio;
use LightManager\Tests\Support\StubPlaylistStorage;
use LightManager\Tests\Support\StubTrackFiles;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Moduł dźwięku po kroku 45: **rysuje i pracuje, gdy go nie widać**.
 *
 * Do kroku 45 był sprawdzianem kontraktu z jednej strony — moduł, który nic nie
 * rysuje. Teraz sprawdza obie: ekran (jak przeglądarka z kroku 21) i takt, czyli
 * zdolność, której przed tym krokiem nie miał żaden moduł.
 *
 * Prawdziwego silnika w tych testach nie ma i nie będzie: test, który go
 * uruchamia, gra muzykę na maszynie, na której akurat biegnie, i zostawia po
 * sobie wątek.
 */
final class AudioModuleTest extends TestCase
{
    private LoopState $state;

    private InMemorySettings $settings;

    private StubAudio $audio;

    private StubPlaylistStorage $storage;

    protected function setUp(): void
    {
        $this->state = new LoopState(new Settings());
        $this->settings = new InMemorySettings($this->state->settings());
        $this->audio = new StubAudio();
        $this->storage = new StubPlaylistStorage([PlaylistEntry::of('a.mp3'), PlaylistEntry::of('b.mp3')]);
    }

    /**
     * Ekran i skrót `Ctrl`+`A` — litera wolna, bo `b` i `d` są zajęte, a sześciu
     * innych zabrania terminal.
     */
    public function testBringsAScreenBehindAFreeShortcutLetter(): void
    {
        $module = $this->module();
        $shortcut = $module->shortcut();

        self::assertSame('audio', $module->id());
        self::assertSame('a', $shortcut->character);
        self::assertTrue($shortcut->ctrl);
        self::assertNotContains('a', ModuleRegistry::FORBIDDEN_CHARACTERS);
        self::assertInstanceOf(ProvidesScreen::class, $module);
        self::assertInstanceOf(ProvidesCommands::class, $module);
        self::assertInstanceOf(NeedsTick::class, $module);
        self::assertDirectoryExists((string) $module->translations());
    }

    /** Ekran jest **jeden**: dwa znaczyłyby dwa kursory i dwa stany playlisty. */
    public function testTheScreenIsBuiltOnce(): void
    {
        $module = $this->module();

        self::assertSame($module->screen(), $module->screen());
    }

    /** Trzy komendy wchodzą pod przestrzeń modułu — tego pilnuje rejestr. */
    public function testCommandsLiveInTheModuleNamespace(): void
    {
        $registry = new CommandRegistry();
        $registry->add('audio', $this->module()->commands());

        self::assertSame([], $registry->rejections());
        self::assertNotNull($registry->find('audio.music'));
        self::assertNotNull($registry->find('audio.volume'));
        self::assertNotNull($registry->find('audio.add'));
    }

    /**
     * Zakładka po kroku 45: tryb wyborem, głośność liczbą, autostart
     * przełącznikiem — a utworu na niej **nie ma**, bo wybiera go playlista.
     */
    public function testSettingsTabTradesTheTrackForAModeAndAnAutostart(): void
    {
        $tab = $this->module()->settingsTab();

        self::assertSame(
            [ModuleSettingKind::Choice, ModuleSettingKind::Number, ModuleSettingKind::Toggle],
            array_map(static fn (ModuleSetting $setting): ModuleSettingKind => $setting->kind, $tab->settings),
        );
        self::assertSame(
            ['mode', 'volume', 'autostart'],
            array_map(static fn (ModuleSetting $setting): string => $setting->key, $tab->settings),
        );
    }

    /** Tryb odtwarzania przyjmuje dokładnie trzy wartości i żadnej więcej. */
    public function testTheModePositionOffersExactlyThreeAnswers(): void
    {
        self::assertSame(['list', 'once', 'repeat'], PlaybackMode::choices());
        self::assertSame(PlaybackMode::choices(), $this->module()->settingsTab()->settings[0]->choices);
    }

    /**
     * Migracja zapętlenia: dawne `loop` rządzi trybem, dopóki nikt nie ruszy
     * nowej pozycji — konfiguracja użytkownika nie zmienia się bez jego udziału.
     */
    public function testTheOldLoopSwitchStillDecidesTheModeUntilTheNewOneIsSet(): void
    {
        $off = (new Settings())->withModuleValue('audio', AudioSettings::LOOP, false);
        $on = (new Settings())->withModuleValue('audio', AudioSettings::LOOP, true);

        self::assertSame(PlaybackMode::StopAfterTrack, AudioSettings::mode($off));
        self::assertSame(PlaybackMode::LoopList, AudioSettings::mode($on));
        self::assertSame(PlaybackMode::LoopList, AudioSettings::mode(new Settings()), 'domyślnie pętla listy');

        $chosen = $on->withModuleValue('audio', AudioSettings::MODE, PlaybackMode::RepeatTrack->value);

        self::assertSame(PlaybackMode::RepeatTrack, AudioSettings::mode($chosen), 'nowy klucz wygrywa ze starym');
    }

    /** Autostart jest domyślnie wyłączony — aplikacja nie gra bez pytania. */
    public function testAutostartIsOffUntilAskedFor(): void
    {
        self::assertFalse(AudioSettings::autostarts(new Settings()));
        self::assertTrue(AudioSettings::autostarts(
            (new Settings())->withModuleValue('audio', AudioSettings::AUTOSTART, true),
        ));
    }

    /** `audio.music` gra to, co wskazuje playlista — utworu już nie wybiera. */
    public function testMusicCommandPlaysWhatThePlaylistPoints(): void
    {
        $outcome = $this->execute('audio.music');

        self::assertSame([['path' => 'a.mp3', 'volume' => 50, 'loop' => false]], $this->audio->played);
        self::assertSame(CommandTransition::Close, $outcome->transition);
        self::assertSame(MessageTone::Info, $outcome->message?->tone);
    }

    /**
     * Druga komenda zatrzymuje, a nie zaczyna od nowa — bo silnik pauzuje,
     * a nie przewija.
     */
    public function testMusicCommandIsAToggle(): void
    {
        $this->execute('audio.music');
        $this->execute('audio.music');

        self::assertCount(1, $this->audio->played, 'drugie wywołanie nie gra od nowa');
        self::assertSame(1, $this->audio->stopCount);

        $this->execute('audio.music');

        self::assertCount(2, $this->audio->played, 'trzecie wznawia');
    }

    /** Powód niepowodzenia wraca **z portu** i staje w pasku stanu bez przeróbek. */
    public function testFailureToPlayComesBackAsAnError(): void
    {
        $this->audio = new StubAudio(problem: 'nie ma czego grać');

        $outcome = $this->execute('audio.music');
        $message = $outcome->message;

        self::assertNotNull($message);
        self::assertSame('nie ma czego grać', $message->text);
        self::assertSame(MessageTone::Error, $message->tone);
    }

    /** `audio.add` dopisuje utwór i zapisuje playlistę — działa spoza okna modułu. */
    public function testAddCommandPutsATrackOnThePlaylist(): void
    {
        $outcome = $this->execute('audio.add', ['path' => '/muzyka/nowy.mp3']);

        self::assertSame(CommandTransition::Close, $outcome->transition);
        self::assertSame([['a.mp3', 'b.mp3', '/muzyka/nowy.mp3']], $this->storage->saved);
    }

    /** Podpowiedzi ścieżek liczy **własny port modułu**, bo do przeglądarki sięgać nie wolno. */
    public function testAddCommandSuggestsPathsFromItsOwnPort(): void
    {
        $command = $this->command('audio.add');

        self::assertInstanceOf(SuggestsArguments::class, $command);
        self::assertSame(['assets/audio/utwor.mp3'], $command->suggestions('path', 'assets/'));
        self::assertSame([], $command->suggestions('level', 'assets/'), 'cudzy argument nic nie podpowiada');
    }

    /** Głośność zmienia się **natychmiast** i zapisuje na dysk. */
    public function testVolumeCommandAppliesAndPersists(): void
    {
        $outcome = $this->execute('audio.volume', ['level' => '70']);

        self::assertSame([70], $this->audio->volumes);
        self::assertSame(70, $this->state->settings()->moduleValue('audio', 'volume'));
        self::assertNotSame([], $this->settings->saved, 'zmiana idzie na dysk od razu');
        self::assertSame(70, $this->settings->saved[0]->moduleValue('audio', 'volume'));
        self::assertSame(CommandTransition::Close, $outcome->transition);
    }

    /**
     * Wartość spoza listy przystanków **nie zamyka okna** i niczego nie zmienia:
     * zapisana wróciłaby z pliku jako domyślna, więc lepiej jej nie przyjąć.
     */
    public function testVolumeOutsideTheStopsIsRejectedWithoutClosingTheWindow(): void
    {
        $outcome = $this->execute('audio.volume', ['level' => '63']);

        self::assertSame(CommandTransition::Stay, $outcome->transition);
        self::assertSame(MessageTone::Error, $outcome->message?->tone);
        self::assertSame([], $this->audio->volumes);
        self::assertSame([], $this->settings->saved);
    }

    /** Podpowiedzi głośności to dokładnie te wartości, które komenda przyjmuje. */
    public function testVolumeSuggestionsMatchTheAcceptedValues(): void
    {
        $command = $this->command('audio.volume');

        self::assertInstanceOf(SuggestsArguments::class, $command);
        self::assertSame(
            array_map(static fn (int $level): string => (string) $level, AudioSettings::VOLUME_CHOICES),
            $command->suggestions('level', ''),
        );
    }

    /** Wartość spoza listy wraca z pliku jako domyślna — stąd bierze się reguła przystanków. */
    public function testSettingsFallBackWhenTheFileHoldsSomethingElse(): void
    {
        $settings = (new Settings())->withModuleValue('audio', 'volume', 63);

        self::assertSame(AudioSettings::DEFAULT_VOLUME, AudioSettings::volume($settings));
    }

    /**
     * Takt modułu prowadzi do playlisty i **do niczego więcej**: moduł jest tu
     * wyłącznie posłańcem.
     */
    public function testTheTickReachesThePlaylist(): void
    {
        $module = $this->module();

        // Komenda i takt muszą trafić w **ten sam** odtwarzacz — inaczej takt
        // pilnowałby stanu, którego komenda nie zmieniła.
        foreach ($module->commands() as $command) {
            if ($command->name() === 'audio.music') {
                $command->execute(new CommandInput([]));
            }
        }

        self::assertSame(['a.mp3'], array_column($this->audio->played, 'path'));

        $this->audio->finish();
        $module->tick(10.0);
        $module->tick(11.0);

        self::assertSame(['a.mp3', 'b.mp3'], array_column($this->audio->played, 'path'));
    }

    private function module(): AudioModule
    {
        return new AudioModule(
            $this->state,
            new StubTranslator(),
            $this->settings,
            $this->audio,
            $this->storage,
            new StubTrackFiles(hints: ['assets/audio/utwor.mp3']),
        );
    }

    private function command(string $name): CommandInterface
    {
        foreach ($this->module()->commands() as $command) {
            if ($command->name() === $name) {
                return $command;
            }
        }

        self::fail('brak komendy ' . $name);
    }

    /** @param array<string, string> $arguments */
    private function execute(string $name, array $arguments = []): CommandOutcome
    {
        return $this->command($name)->execute(new CommandInput($arguments));
    }
}
