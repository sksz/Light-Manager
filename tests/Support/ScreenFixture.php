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
use LightManager\Application\UseCase\RestoreDefaultSettingsUseCase;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Presentation\BrowserModule;
use LightManager\Module\FileInfo\Presentation\FileInfoModule;
use LightManager\Presentation\Cli\Bootstrap;
use LightManager\Presentation\Cli\Command\QuitCommand;
use LightManager\Presentation\Cli\Command\ScreenCommand;
use LightManager\Presentation\Cli\Command\SettingCommand;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Cli\ProblemPresenter;
use LightManager\Presentation\Cli\Screen\HelpScreen;
use LightManager\Presentation\Cli\Screen\SettingsScreen;
use LightManager\Presentation\Cli\ScreenStack;
use LightManager\Presentation\Cli\StartupScreen;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\Overlay\CommandOverlay;
use LightManager\Presentation\Ui\Overlay\MenuOverlay;
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
 * i mapą skrótów. Od kroku 21 jednym z nich jest **przeglądarka plików**, i to
 * prawdziwy `BrowserModule`, a nie sobowtór: dostaje repozytorium w pamięci
 * i katalog startowy, więc składa się bez dotykania dysku, ale przechodzi tę samą
 * drogę, co w aplikacji. Dzięki temu test widzi także dno stosu wybrane
 * z konfiguracji.
 */
final class ScreenFixture
{
    public readonly LoopState $state;

    /** Ekran przeglądarki — od kroku 21 pochodzi z modułu, więc widziany kontraktem. */
    public readonly ScreenInterface $browser;

    public readonly SettingsScreen $settings;

    public readonly HelpScreen $help;

    public readonly ScreenStack $screens;

    public readonly InputHandler $input;

    public readonly CommandOverlay $commands;

    /** Menu kontekstowe — drugie wejście do tego samego rejestru (krok 32). */
    public readonly MenuOverlay $menu;

    public readonly ModuleRegistry $modules;

    /** Ekran opisu pliku — od kroku 25 pochodzi z modułu, więc widziany kontraktem. */
    public readonly ScreenInterface $fileInfo;

    public readonly CommandRegistry $commandRegistry;

    /** Ekran, który stanął na dnie stosu — i powód, gdy nie ten, o który proszono. */
    public readonly StartupScreen $startup;

    public function __construct(
        Directory $directory,
        public readonly InMemoryDirectoryRepository $directories,
        public readonly InMemorySettings $settingsStore = new InMemorySettings(),
        public readonly StubFileInspector $inspector = new StubFileInspector('PDF document, version 1.7'),
        public readonly InMemoryCommandHistory $history = new InMemoryCommandHistory(),
        public readonly StubFileStat $stats = new StubFileStat(),
        public readonly StubChecksums $checksums = new StubChecksums(),
        public readonly StubBackgroundProcess $processes = new StubBackgroundProcess(),
    ) {
        $translator = new StubTranslator();
        $themes = new FixedThemes();

        $this->state = new LoopState($settingsStore->load($themes->names())->settings);

        // Moduł opisu pliku składa się sam (krok 25), więc zestaw podaje mu
        // wyłącznie porty — a testy dostają ekran przez ten sam `screen()`,
        // którym dostaje go aplikacja.
        $fileInfo = new FileInfoModule(
            $this->state,
            $translator,
            $settingsStore,
            new StubImagePreview(),
            $processes,
            $inspector,
            $stats,
            $checksums,
        );
        $this->fileInfo = $fileInfo->screen();

        // Katalog podany przez test jest katalogiem startowym modułu; repozytorium
        // musi go znać, bo moduł otwiera go tak samo, jak zrobiłby to na dysku.
        $directories->add($directory->path()->value, $directory->entries());

        $browser = new BrowserModule(
            $this->state,
            $translator,
            $settingsStore,
            new StubImagePreview(),
            $directories,
            $directory->path(),
        );

        $this->modules = new ModuleRegistry(
            [$browser, $fileInfo],
            $settingsStore->current()->modules,
            Bootstrap::LAST_RESORT_MODULE,
        );

        $this->browser = $browser->screen();

        $this->settings = new SettingsScreen(
            $this->state,
            new ChangeSettingUseCase($settingsStore, $themes, $translator, self::startupModules($this->modules)),
            new RestoreDefaultSettingsUseCase($settingsStore, $translator),
            new ChangeModuleSettingUseCase($settingsStore, $translator),
            $translator,
            $settingsStore,
            self::settingsTabs($this->modules),
            $this->modules,
        );

        $this->help = new HelpScreen($settingsStore, $translator, Bootstrap::VERSION, 'Sixel');

        $this->commandRegistry = new CommandRegistry();
        $change = new ChangeSettingUseCase($settingsStore, $themes, $translator, self::startupModules($this->modules));
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
        $this->menu = new MenuOverlay($this->commandRegistry, $translator);

        $this->help->knowAbout(
            [$this->settings, $this->help],
            InputHandler::globalBindings(),
            [
                'layout.zone.command' => $this->commands->bindings(),
                'menu.title' => $this->menu->bindings(),
            ],
        );
        $this->help->knowAboutModules($this->modules->accepted());

        $this->startup = StartupScreen::choose(
            $this->modules,
            $this->state->settings()->startupModule,
            Bootstrap::LAST_RESORT_MODULE,
        );

        $this->screens = new ScreenStack($this->startup->screen);
        $this->input = new InputHandler(
            $this->screens,
            $this->help,
            $this->settings,
            new ProblemPresenter($translator),
            $this->commands,
            self::moduleScreens($this->modules),
            null,
            $this->menu,
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

    /**
     * Dopuszczalne wartości klucza `startupModule` — tak samo liczone, jak
     * w `Bootstrap`.
     *
     * @return list<string>
     */
    public static function startupModules(ModuleRegistry $modules): array
    {
        $ids = [];

        foreach ($modules->accepted() as $module) {
            if ($module instanceof ProvidesScreen) {
                $ids[] = $module->id();
            }
        }

        return $ids;
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
