<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use LightManager\Application\Command\CommandHistory;
use LightManager\Application\Command\CommandLineParser;
use LightManager\Application\Command\CommandRegistry;
use LightManager\Application\Dto\Language;
use LightManager\Application\Dto\LoadedSettings;
use LightManager\Application\Dto\SettingKey;
use LightManager\Application\Dto\SettingsTab;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleRegistry;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Application\UseCase\ChangeSettingUseCase;
use LightManager\Application\UseCase\LoadSettingsUseCase;
use LightManager\Application\UseCase\MoveSelectionUseCase;
use LightManager\Application\UseCase\NavigateIntoDirectoryUseCase;
use LightManager\Application\UseCase\NavigateUpUseCase;
use LightManager\Application\UseCase\OpenStartingDirectoryUseCase;
use LightManager\Application\UseCase\PreviewSelectedEntryUseCase;
use LightManager\Application\UseCase\RestoreDefaultSettingsUseCase;
use LightManager\Application\UseCase\ToggleHiddenEntriesUseCase;
use LightManager\Domain\Repository\DirectoryRepositoryInterface;
use LightManager\Domain\ValueObject\DirectoryPath;
use LightManager\Domain\ValueObject\Message;
use LightManager\Infrastructure\Config\CommandHistoryService;
use LightManager\Infrastructure\Config\SettingsService;
use LightManager\Infrastructure\Filesystem\EntryComparator;
use LightManager\Infrastructure\Filesystem\FilesystemDirectoryRepository;
use LightManager\Infrastructure\I18n\TranslatorService;
use LightManager\Infrastructure\Imagick\ImagePreviewService;
use LightManager\Infrastructure\Rendering\RendererService;
use LightManager\Infrastructure\Rendering\ThemeService;
use LightManager\Infrastructure\Terminal\SixelCapabilityService;
use LightManager\Infrastructure\Terminal\TerminalService;
use LightManager\Infrastructure\Terminal\TerminalSizeService;
use LightManager\Module\FileInfo\Application\UseCase\InspectSelectedEntryUseCase;
use LightManager\Module\FileInfo\Infrastructure\FileInspectorService;
use LightManager\Module\FileInfo\Presentation\Command\JumpCommand;
use LightManager\Module\FileInfo\Presentation\FileInfoModule;
use LightManager\Module\FileInfo\Presentation\FileInfoScreen;
use LightManager\Presentation\Cli\Command\QuitCommand;
use LightManager\Presentation\Cli\Command\ScreenCommand;
use LightManager\Presentation\Cli\Command\SettingCommand;
use LightManager\Presentation\Cli\Screen\BrowserScreen;
use LightManager\Presentation\Cli\Screen\HelpScreen;
use LightManager\Presentation\Cli\Screen\SettingsScreen;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\Overlay\CommandOverlay;
use LightManager\Presentation\Ui\ScreenInterface;

/**
 * Jedyne miejsce, w którym aplikacja wie, które klasy `Infrastructure` stoją
 * za którymi portami. Nie jest usługą i nie dziedziczy po `AbstractSingleton` —
 * to jednorazowa procedura uruchamiana z punktu wejścia w `bin/`.
 *
 * Od kroku 18 składa też **ekrany**. Kolejność jest tu wymuszona: stan pętli
 * musi powstać przed nimi, bo z niego czytają, a okno pomocy musi powstać po
 * nich, bo składa spis z ich wiązań klawiszy.
 */
final class Bootstrap
{
    public const VERSION = '0.20.0';

    /**
     * Historia komend żyje tak długo, jak proces, a zapisuje się przy jego
     * końcu — więc `shutdown()` musi mieć do niej dostęp. To jedyny stan
     * trzymany przez bootstrap i jedyny, który ma powód tu być: nikt inny nie
     * wie, kiedy aplikacja się kończy.
     */
    private static ?CommandHistory $history = null;

    /**
     * Kolejność jest wymuszona jawnie, bo każda z tych usług ma w konstruktorze
     * efekt uboczny wymagany przed pętlą: tryb surowy terminala, wykrycie trybu
     * renderowania, przejęcie ekranu.
     *
     * Konfiguracja wchodzi przed renderowaniem, bo to ona wybiera motyw — a
     * odczytana po rendererze zostałaby zapamiętana **bez sprawdzenia nazwy
     * palety**, więc plik z literówką w kluczu `theme` przeszedłby bez słowa.
     */
    public static function boot(): void
    {
        TerminalService::getInstance();
        SixelCapabilityService::getInstance();
        self::loadSettings();
        RendererService::getInstance();
    }

    public static function createGameLoop(): GameLoop
    {
        $directories = self::createDirectoryRepository();
        $settings = SettingsService::getInstance();
        $translator = TranslatorService::getInstance();
        $state = self::createInitialState($directories);

        // Moduły powstają **przed** ekranami rdzenia, bo ich napisy muszą wejść
        // do katalogu przed pierwszym tłumaczeniem, a ich zakładki — trafić do
        // ekranu ustawień w chwili jego budowy.
        $modules = new ModuleRegistry(self::createModules($state, $directories, $translator), $settings->current()->modules);
        self::registerTranslations($modules, $translator);

        $browser = new BrowserScreen(
            $state,
            new MoveSelectionUseCase(),
            new NavigateIntoDirectoryUseCase($directories),
            new NavigateUpUseCase($directories),
            new ToggleHiddenEntriesUseCase($directories),
            new ChangeSettingUseCase($settings, ThemeService::getInstance(), $translator),
            $translator,
        );

        // Ekran modułu otwarty pierwszym naciśnięciem skrótu ma zastać kontekst
        // wypełniony, a nie pusty — a pierwsza klatka jeszcze nie padła.
        $browser->publishContext();

        $settingsScreen = new SettingsScreen(
            $state,
            new ChangeSettingUseCase($settings, ThemeService::getInstance(), $translator),
            new RestoreDefaultSettingsUseCase($settings, $translator),
            new ChangeModuleSettingUseCase($settings, $translator),
            $translator,
            self::settingsTabs($modules),
            $modules,
        );

        $help = new HelpScreen(
            $settings,
            $translator,
            self::VERSION,
            SixelCapabilityService::getInstance()->detect()->name,
        );

        $commands = self::createCommandOverlay($state, $settings, $translator, $modules);

        // Pomoc składa spis klawiszy z wiązań, więc musi poznać pozostałe ekrany
        // — wraz ze sobą, bo własne klawisze też są częścią spisu — oraz okno
        // komend, które ekranem nie jest, a klawisze ma. Moduły dostają własne
        // zakładki, więc idą osobno.
        $help->knowAbout(
            [$browser, $settingsScreen, $help],
            InputHandler::globalBindings(),
            ['layout.zone.command' => $commands->bindings()],
        );
        $help->knowAboutModules($modules->accepted());

        $screens = new ScreenStack($browser);

        self::reportModuleProblems($modules, $state, $translator);

        return new GameLoop(
            TerminalService::getInstance(),
            new FrameComposer(
                RendererService::getInstance(),
                TerminalSizeService::getInstance(),
                $translator,
                new PreviewSelectedEntryUseCase(ImagePreviewService::getInstance(), $translator),
                InputHandler::globalBindings(),
            ),
            $screens,
            new InputHandler(
                $screens,
                $help,
                $settingsScreen,
                self::problemPresenter(),
                $commands,
                self::moduleScreens($modules),
            ),
            $state,
        );
    }

    /**
     * **Jedyne miejsce w rdzeniu, które zna konkretne klasy modułów.**
     *
     * Dopisanie modułu ze wszystkimi pięcioma punktami zaczepienia — oknem,
     * zakładką ustawień, zakładką pomocy, napisami i komendami — kosztuje jedną
     * pozycję na tej liście. To jest miara powodzenia kroku 20 i zarazem jedyne
     * miejsce, w którym da się ją złamać.
     *
     * Moduł jest **zwykłym obiektem** tworzonym `new`-em, z wstrzykniętymi
     * zależnościami; nie jest Singletonem i nie woła `getInstance()`. Singletonem
     * pozostaje usługa w jego własnej warstwie `Infrastructure`.
     *
     * @return list<ModuleInterface>
     */
    private static function createModules(
        LoopState $state,
        DirectoryRepositoryInterface $directories,
        TranslatorService $translator,
    ): array {
        return [
            new FileInfoModule(
                new FileInfoScreen(
                    new InspectSelectedEntryUseCase(
                        FileInspectorService::getInstance(),
                        SettingsService::getInstance(),
                    ),
                    $translator,
                ),
                new JumpCommand($state, $directories, $translator),
            ),
        ];
    }

    /** Napisy modułów wchodzą do katalogu przed pierwszym tłumaczeniem. */
    private static function registerTranslations(ModuleRegistry $modules, TranslatorService $translator): void
    {
        foreach ($modules->accepted() as $module) {
            $directory = $module->translations();

            if ($directory !== null) {
                $translator->addSource($module->id(), $directory);
            }
        }
    }

    /**
     * Zakładki ekranu ustawień: dwie rdzeniowe, spis modułów, na końcu zakładki
     * poszczególnych modułów.
     *
     * Spis stoi **przed** zakładkami modułów, bo działa jak nagłówek sekcji:
     * najpierw „co jest włączone”, potem ustawienia każdego z osobna.
     *
     * @return list<SettingsTab>
     */
    private static function settingsTabs(ModuleRegistry $modules): array
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

    /** @return array<string, ScreenInterface> litera skrótu → ekran modułu */
    private static function moduleScreens(ModuleRegistry $modules): array
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
     * Odrzucone moduły i napisy pominięte przy scalaniu katalogów — jeden
     * komunikat w tonie ostrzeżenia, w pasku stanu przy starcie.
     *
     * Pasek stanu niesie jeden komunikat, więc ostrzeżenie o modułach ustępuje
     * temu, co powiedział o sobie start aplikacji: nieotwartemu katalogowi i
     * uwadze do pliku konfiguracji. Moduł, który odpadł, nie jest pilniejszy od
     * katalogu, którego użytkownik właśnie nie widzi.
     */
    private static function reportModuleProblems(
        ModuleRegistry $modules,
        LoopState $state,
        TranslatorService $translator,
    ): void {
        if ($state->message() !== null) {
            return;
        }

        $rejections = $modules->rejections();
        $ignored = $translator->ignoredKeys();

        if ($rejections !== []) {
            $names = array_map(
                static fn (\LightManager\Application\Module\ModuleRejection $rejection): string => $rejection->id
                    . ' (' . $translator->translate($rejection->reasonKey) . ')',
                $rejections,
            );

            $state->report(
                Message::warning($translator->plural('module.rejected', count($rejections), [
                    'modules' => implode(', ', $names),
                ])),
                microtime(true),
            );

            return;
        }

        if ($ignored !== []) {
            $state->report(
                Message::warning($translator->plural('module.lang.ignored', count($ignored), [
                    'keys' => implode(', ', $ignored),
                ])),
                microtime(true),
            );
        }
    }

    /**
     * Okno komend wraz z rejestrem, parserem i historią.
     *
     * Kolejność jest wymuszona: rejestr musi znać komplet komend, zanim okno
     * poprosi o **podpowiedzi stałe** (`prepare()`), bo te liczą się raz i już
     * się nie odświeżą. Komendy zmieniające ustawienia dostają stan pętli, żeby
     * zmiana obowiązywała od następnej klatki, a nie od następnego uruchomienia.
     */
    private static function createCommandOverlay(
        LoopState $state,
        SettingsService $settings,
        TranslatorService $translator,
        ModuleRegistry $modules,
    ): CommandOverlay {
        $themes = ThemeService::getInstance();
        $change = new ChangeSettingUseCase($settings, $themes, $translator);
        $registry = new CommandRegistry();

        $registry->add(CommandRegistry::CORE, [
            new ScreenCommand('core.help', 'help'),
            new ScreenCommand('core.settings', 'settings'),
            new SettingCommand('core.theme', SettingKey::Theme, 'theme', $themes->names(), $state, $change),
            new SettingCommand('core.language', SettingKey::Language, 'language', self::languageCodes(), $state, $change),
            new QuitCommand(),
        ]);

        // Komendy modułu wchodzą pod jego własną przestrzenią nazw — nazwy spoza
        // niej odsiewa rejestr, dokładnie tak samo, jak katalog napisów odsiewa
        // klucze spoza przedrostka modułu.
        foreach ($modules->accepted() as $module) {
            if ($module instanceof ProvidesCommands) {
                $registry->add($module->id(), $module->commands());
            }
        }

        self::$history = new CommandHistory(CommandHistoryService::getInstance());

        $overlay = new CommandOverlay(
            $registry,
            new CommandLineParser($translator),
            self::$history,
            $translator,
        );
        $overlay->prepare();

        return $overlay;
    }

    /** @return list<string> */
    private static function languageCodes(): array
    {
        return array_map(static fn (Language $language): string => $language->value, Language::cases());
    }

    /**
     * Napis dla użytkownika z wyjątku — potrzebny także punktowi wejścia w
     * `bin/`, gdy start się nie powiedzie i pętla nigdy nie ruszy.
     */
    public static function problemPresenter(): ProblemPresenter
    {
        return new ProblemPresenter(TranslatorService::getInstance());
    }

    /**
     * Wynik jest zapamiętany po stronie usługi konfiguracji, więc powtórne
     * wywołanie nie dotyka dysku i oddaje ten sam komplet wraz z ewentualnym
     * ostrzeżeniem.
     */
    private static function loadSettings(): LoadedSettings
    {
        return (new LoadSettingsUseCase(
            SettingsService::getInstance(),
            ThemeService::getInstance(),
        ))->execute();
    }

    private static function createDirectoryRepository(): DirectoryRepositoryInterface
    {
        return new FilesystemDirectoryRepository(EntryComparator::create());
    }

    private static function createInitialState(DirectoryRepositoryInterface $directories): LoopState
    {
        $loaded = self::loadSettings();
        $requested = self::startingPath();
        $opened = (new OpenStartingDirectoryUseCase($directories))
            ->execute($requested, $loaded->settings->showHiddenEntries);

        $state = new LoopState($opened, $loaded->settings);
        $now = microtime(true);

        // Nieotwarty katalog jest ważniejszy od uwag do pliku konfiguracji:
        // pasek stanu niesie jeden komunikat, więc pierwszeństwo ma ten, który
        // mówi o tym, co użytkownik właśnie widzi.
        if (!$opened->path()->equals($requested)) {
            $state->reportProblem(
                TranslatorService::getInstance()->translate('problem.directory.fallback', [
                    'requested' => $requested->value,
                    'opened' => $opened->path()->value,
                ]),
                $now,
            );

            return $state;
        }

        if ($loaded->problem !== null) {
            $state->report(Message::warning($loaded->problem), $now);
        }

        return $state;
    }

    /** Katalog roboczy procesu; gdy nie da się go ustalić — korzeń systemu plików. */
    private static function startingPath(): DirectoryPath
    {
        $workingDirectory = getcwd();

        return $workingDirectory === false
            ? DirectoryPath::root()
            : new DirectoryPath($workingDirectory);
    }

    /**
     * Historia dopisuje się przy wyjściu — także wtedy, gdy bufor nie zdążył się
     * zapełnić. Zapis idzie **przed** przywróceniem terminala, bo to on jest
     * ostatnią czynnością, po której proces może zniknąć.
     */
    public static function shutdown(): void
    {
        self::$history?->flush();

        TerminalService::getInstance()->restore();
    }
}
