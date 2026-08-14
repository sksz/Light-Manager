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
use LightManager\Application\Module\ModuleSettingKind;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Module\Audio\Application\AudioSettings;
use LightManager\Module\Audio\Presentation\AudioModule;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubAudio;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Moduł dźwięku jest **sprawdzianem kontraktu modułu z drugiej strony niż krok
 * 21**: tamten pytał, czy kontrakt udźwignie główną funkcję aplikacji, ten —
 * czy udźwignie moduł, który nic nie rysuje.
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

    protected function setUp(): void
    {
        $this->state = new LoopState(new Settings());
        $this->settings = new InMemorySettings($this->state->settings());
        $this->audio = new StubAudio();
    }

    /** Moduł bez ekranu i bez skrótu — kontrakt na to pozwala i nic z tego nie wynika. */
    public function testBringsNoScreenAndTakesNoShortcut(): void
    {
        $module = $this->module();

        self::assertSame('audio', $module->id());
        self::assertNull($module->shortcut(), 'skrót bez ekranu zajmowałby literę i nie robił nic');
        self::assertNotContains(ProvidesScreen::class, class_implements($module), 'moduł nie rysuje ekranu');
        self::assertInstanceOf(ProvidesCommands::class, $module);
        self::assertDirectoryExists((string) $module->translations());
    }

    /** Obie komendy wchodzą pod przestrzeń modułu — tego pilnuje rejestr. */
    public function testCommandsLiveInTheModuleNamespace(): void
    {
        $registry = new CommandRegistry();
        $registry->add('audio', $this->module()->commands());

        self::assertSame([], $registry->rejections());
        self::assertNotNull($registry->find('audio.music'));
        self::assertNotNull($registry->find('audio.volume'));
    }

    /** Zakładka ustawień: ścieżka tekstem, głośność liczbą z listy, zapętlenie przełącznikiem. */
    public function testSettingsTabDeclaresThreePositions(): void
    {
        $tab = $this->module()->settingsTab();
        $kinds = array_map(
            static fn (\LightManager\Application\Module\ModuleSetting $setting): ModuleSettingKind => $setting->kind,
            $tab->settings,
        );

        self::assertSame(
            [ModuleSettingKind::Text, ModuleSettingKind::Number, ModuleSettingKind::Toggle],
            $kinds,
        );
        self::assertSame(
            ['track', 'volume', 'loop'],
            array_map(
                static fn (\LightManager\Application\Module\ModuleSetting $setting): string => $setting->key,
                $tab->settings,
            ),
        );
    }

    /**
     * `audio.music` gra utworem i wartościami **z ustawień**, a nie ze stałych
     * wpisanych w komendę.
     */
    public function testMusicCommandPlaysWhatTheSettingsSay(): void
    {
        $this->state->applySettings(
            (new Settings())
                ->withModuleValue('audio', 'track', '/muzyka/utwor.mp3')
                ->withModuleValue('audio', 'volume', 30)
                ->withModuleValue('audio', 'loop', false),
        );

        $outcome = $this->execute('audio.music');

        self::assertSame(
            [['path' => '/muzyka/utwor.mp3', 'volume' => 30, 'loop' => false]],
            $this->audio->played,
        );
        self::assertSame(CommandTransition::Close, $outcome->transition);
        self::assertSame(MessageTone::Info, $outcome->message?->tone);
    }

    /** Bez ustawień gra utwór domyślny — ten z katalogu `assets/audio`. */
    public function testWithoutSettingsItPlaysTheDefaultTrack(): void
    {
        $this->execute('audio.music');

        self::assertSame(AudioSettings::DEFAULT_TRACK, $this->audio->played[0]['path']);
        self::assertSame(AudioSettings::DEFAULT_VOLUME, $this->audio->played[0]['volume']);
        self::assertTrue($this->audio->played[0]['loop']);
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
        $settings = (new Settings())
            ->withModuleValue('audio', 'volume', 63)
            ->withModuleValue('audio', 'track', '');

        self::assertSame(AudioSettings::DEFAULT_VOLUME, AudioSettings::volume($settings));
        self::assertSame(AudioSettings::DEFAULT_TRACK, AudioSettings::track($settings));
        self::assertTrue(AudioSettings::loops($settings));
    }

    private function module(): AudioModule
    {
        return new AudioModule($this->state, new StubTranslator(), $this->settings, $this->audio);
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
