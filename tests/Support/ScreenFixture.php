<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Command\CommandHistory;
use LightManager\Application\Command\CommandLineParser;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Dto\SettingKey;
use LightManager\Application\Dto\SettingsTab;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleRegistry;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Ui\Rect;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Application\UseCase\ChangeSettingUseCase;
use LightManager\Application\UseCase\MoveSelectionUseCase;
use LightManager\Application\UseCase\NavigateIntoDirectoryUseCase;
use LightManager\Application\UseCase\NavigateUpUseCase;
use LightManager\Application\UseCase\RestoreDefaultSettingsUseCase;
use LightManager\Application\UseCase\ToggleHiddenEntriesUseCase;
use LightManager\Domain\Aggregate\Directory;
use LightManager\Module\FileInfo\Application\UseCase\InspectSelectedEntryUseCase;
use LightManager\Module\FileInfo\Presentation\Command\JumpCommand;
use LightManager\Module\FileInfo\Presentation\FileInfoModule;
use LightManager\Module\FileInfo\Presentation\FileInfoScreen;
use LightManager\Presentation\Cli\Command\QuitCommand;
use LightManager\Presentation\Cli\Command\ScreenCommand;
use LightManager\Presentation\Cli\Command\SettingCommand;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Cli\ProblemPresenter;
use LightManager\Presentation\Cli\Screen\BrowserScreen;
use LightManager\Presentation\Cli\Screen\HelpScreen;
use LightManager\Presentation\Cli\Screen\SettingsScreen;
use LightManager\Presentation\Cli\ScreenStack;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\Overlay\CommandOverlay;
use LightManager\Presentation\Ui\ScreenInterface;

/**
 * Komplet ekranów wraz z ich zależnościami — składany raz, dla testów, które
 * sprawdzają zachowanie aplikacji, a nie pojedynczej klasy.
 *
 * Odpowiednik `Bootstrap` bez systemu plików, terminala i Imagicka. Powstał
 * w kroku 18: ekrany są od niego obiektami z kilkoma zależnościami każdy, więc
 * budowanie ich w `setUp()` każdego testu z osobna byłoby przepisywaniem tej
 * samej listy.
 *
 * Od kroku 20 składa też **moduły** — wraz z rejestrem, zakładkami ustawień
 * i mapą skrótów. Dzięki temu test przechodzi tę samą drogę, co aplikacja:
 * gdyby moduł dało się podłączyć wyłącznie ręcznym obejściem w `Bootstrap`,
 * ten podwójny by tego nie ukrył.
 */
final class ScreenFixture
{
    public readonly LoopState $state;

    public readonly BrowserScreen $browser;

    public readonly SettingsScreen $settings;

    public readonly HelpScreen $help;

    public readonly ScreenStack $screens;

    public readonly InputHandler $input;

    public readonly CommandOverlay $commands;

    public readonly ModuleRegistry $modules;

    public readonly FileInfoScreen $fileInfo;

    public readonly CommandRegistry $commandRegistry;

    public function __construct(
        Directory $directory,
        public readonly InMemoryDirectoryRepository $directories,
        public readonly InMemorySettings $settingsStore = new InMemorySettings(),
        public readonly StubFileInspector $inspector = new StubFileInspector('PDF document, version 1.7'),
        public readonly InMemoryCommandHistory $history = new InMemoryCommandHistory(),
    ) {
        $translator = new StubTranslator();
        $themes = new FixedThemes();

        $this->state = new LoopState($directory, $settingsStore->load($themes->names())->settings);

        $this->fileInfo = new FileInfoScreen(
            new InspectSelectedEntryUseCase($inspector, $settingsStore),
            $translator,
        );

        $this->modules = new ModuleRegistry(
            [new FileInfoModule($this->fileInfo, new JumpCommand($this->state, $directories, $translator))],
            $settingsStore->current()->modules,
        );

        $this->browser = new BrowserScreen(
            $this->state,
            new MoveSelectionUseCase(),
            new NavigateIntoDirectoryUseCase($directories),
            new NavigateUpUseCase($directories),
            new ToggleHiddenEntriesUseCase($directories),
            new ChangeSettingUseCase($settingsStore, $themes, $translator),
            $translator,
        );
        $this->browser->publishContext();

        $this->settings = new SettingsScreen(
            $this->state,
            new ChangeSettingUseCase($settingsStore, $themes, $translator),
            new RestoreDefaultSettingsUseCase($settingsStore, $translator),
            new ChangeModuleSettingUseCase($settingsStore, $translator),
            $translator,
            self::settingsTabs($this->modules),
            $this->modules,
        );

        $this->help = new HelpScreen($settingsStore, $translator, '0.20.0', 'Sixel');

        $this->commandRegistry = new CommandRegistry();
        $change = new ChangeSettingUseCase($settingsStore, $themes, $translator);
        $this->commandRegistry->add(CommandRegistry::CORE, [
            new ScreenCommand('core.help', 'help'),
            new ScreenCommand('core.settings', 'settings'),
            new SettingCommand('core.theme', SettingKey::Theme, 'theme', $themes->names(), $this->state, $change),
            new QuitCommand(),
        ]);

        foreach ($this->modules->accepted() as $module) {
            if ($module instanceof ProvidesCommands) {
                $this->commandRegistry->add($module->id(), $module->commands());
            }
        }

        $this->commands = new CommandOverlay(
            $this->commandRegistry,
            new CommandLineParser($translator),
            new CommandHistory($history),
            $translator,
        );
        $this->commands->prepare();

        $this->help->knowAbout(
            [$this->browser, $this->settings, $this->help],
            InputHandler::globalBindings(),
            ['layout.zone.command' => $this->commands->bindings()],
        );
        $this->help->knowAboutModules($this->modules->accepted());

        $this->screens = new ScreenStack($this->browser);
        $this->input = new InputHandler(
            $this->screens,
            $this->help,
            $this->settings,
            new ProblemPresenter($translator),
            $this->commands,
            self::moduleScreens($this->modules),
        );
    }

    /**
     * Zakładki ustawień w tej samej kolejności, co w `Bootstrap`: rdzeniowe,
     * spis modułów, zakładki modułów.
     *
     * @return list<SettingsTab>
     */
    public static function settingsTabs(ModuleRegistry $modules): array
    {
        $tabs = SettingsTab::coreTabs();
        $tabs[] = SettingsTab::modules(count($modules->declared()));

        foreach ($modules->accepted() as $module) {
            if ($module instanceof ProvidesSettingsTab) {
                $tabs[] = SettingsTab::ofModule($module->id(), $module->settingsTab());
            }
        }

        return $tabs;
    }

    /** @return array<string, ScreenInterface> */
    public static function moduleScreens(ModuleRegistry $modules): array
    {
        $screens = [];

        foreach ($modules->shortcuts() as $character => $module) {
            if ($module instanceof ProvidesScreen) {
                $screens[$character] = $module->screen();
            }
        }

        return $screens;
    }

    /** Moduł o podanym identyfikatorze — dla testów, które sprawdzają deklarację. */
    public function module(string $id): ?ModuleInterface
    {
        return $this->modules->find($id);
    }

    /** Prostokąt środkowego panelu o zadanym rozmiarze — wejście do `draw()`. */
    public static function panel(int $rows = 10, int $columns = 40): Rect
    {
        return new Rect(0, 2, $rows, $columns);
    }
}
