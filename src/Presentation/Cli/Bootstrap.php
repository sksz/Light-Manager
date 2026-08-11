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
use LightManager\Application\Module\ModuleRejection;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Application\UseCase\ChangeSettingUseCase;
use LightManager\Application\UseCase\LoadSettingsUseCase;
use LightManager\Application\UseCase\RestoreDefaultSettingsUseCase;
use LightManager\Domain\ValueObject\Message;
use LightManager\Infrastructure\Config\CommandHistoryService;
use LightManager\Infrastructure\Config\SettingsService;
use LightManager\Infrastructure\I18n\TranslatorService;
use LightManager\Infrastructure\Imagick\ImagePreviewService;
use LightManager\Infrastructure\Rendering\RendererService;
use LightManager\Infrastructure\Rendering\ThemeService;
use LightManager\Infrastructure\Terminal\SixelCapabilityService;
use LightManager\Infrastructure\Terminal\TerminalService;
use LightManager\Infrastructure\Terminal\TerminalSizeService;
use LightManager\Module\Browser\Presentation\BrowserModule;
use LightManager\Module\FileInfo\Presentation\FileInfoModule;
use LightManager\Presentation\Cli\Command\QuitCommand;
use LightManager\Presentation\Cli\Command\ScreenCommand;
use LightManager\Presentation\Cli\Command\SettingCommand;
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
 *
 * Od kroku 21 nie stoi tu ani jeden ekran przeglądarki plików i ani jedna klasa
 * wiedząca, czym jest katalog. Menadżer plików jest modułem i wchodzi tą samą
 * jedną linijką, co każdy inny; różni się od pozostałych wyłącznie tym, że jego
 * identyfikator idzie do rejestru jako **moduł ostatniej szansy**.
 */
final class Bootstrap
{
    public const VERSION = '0.21.0';

    /**
     * Moduł, do którego aplikacja wraca, gdy moduł domyślny okaże się
     * niedostępny — wyłączony, odrzucony, nieobecny albo bez ekranu.
     *
     * Identyfikator stoi **tutaj**, a nie w `ModuleRegistry`: warstwa
     * `Application/Module` nie zna nazwy żadnego konkretnego modułu i nie ma
     * powodu jej poznać. Nieobecność tego modułu na liście poniżej jest błędem
     * programistycznym, nie sytuacją użytkownika — łapie go test.
     */
    public const LAST_RESORT_MODULE = 'browser';

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
        $settings = SettingsService::getInstance();
        $translator = TranslatorService::getInstance();
        $state = self::createInitialState();

        // Moduły powstają **przed** ekranami rdzenia, bo ich napisy muszą wejść
        // do katalogu przed pierwszym tłumaczeniem, a ich zakładki — trafić do
        // ekranu ustawień w chwili jego budowy.
        $modules = new ModuleRegistry(
            self::createModules($state, $translator, $settings),
            $settings->current()->modules,
            self::LAST_RESORT_MODULE,
        );
        self::registerTranslations($modules, $translator);

        $settingsScreen = new SettingsScreen(
            $state,
            new ChangeSettingUseCase(
                $settings,
                ThemeService::getInstance(),
                $translator,
                self::startupModules($modules),
            ),
            new RestoreDefaultSettingsUseCase($settings, $translator),
            new ChangeModuleSettingUseCase($settings, $translator),
            $translator,
            $settings,
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
        $floor = self::floor($modules, $state, $translator);

        // Pomoc składa spis klawiszy z wiązań, więc musi poznać pozostałe ekrany
        // — wraz ze sobą, bo własne klawisze też są częścią spisu — oraz okno
        // komend, które ekranem nie jest, a klawisze ma. Ekrany modułów idą przez
        // `knowAboutModules()`, bo dostają własne zakładki.
        $help->knowAbout(
            [$settingsScreen, $help],
            InputHandler::globalBindings(),
            ['layout.zone.command' => $commands->bindings()],
        );
        $help->knowAboutModules($modules->accepted());

        $screens = new ScreenStack($floor);

        self::reportModuleProblems($modules, $state, $translator);

        return new GameLoop(
            TerminalService::getInstance(),
            new FrameComposer(
                RendererService::getInstance(),
                TerminalSizeService::getInstance(),
                $translator,
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
     * Przeglądarka dostaje wyłącznie rzeczy rdzenia i resztę składa sobie sama —
     * inaczej `Bootstrap` musiałby poznać repozytorium katalogów i ścieżkę
     * katalogu, czyli dokładnie tę wiedzę, którą krok 21 z rdzenia wyjmuje.
     *
     * @return list<ModuleInterface>
     */
    private static function createModules(
        LoopState $state,
        TranslatorService $translator,
        SettingsService $settings,
    ): array {
        return [
            new BrowserModule($state, $translator, $settings, ImagePreviewService::getInstance()),
            new FileInfoModule($translator, $settings, ImagePreviewService::getInstance()),
        ];
    }

    /**
     * Ekran, na którym stoi dno stosu — czyli to, co widać po starcie i dokąd
     * wraca `Esc`. Sam wybór robi `StartupScreen`; tutaj zostaje wyłącznie
     * postawienie komunikatu, bo to `Bootstrap` trzyma stan pętli.
     */
    private static function floor(
        ModuleRegistry $modules,
        LoopState $state,
        TranslatorService $translator,
    ): ScreenInterface {
        $startup = StartupScreen::choose($modules, $state->settings()->startupModule, self::LAST_RESORT_MODULE);

        if ($startup->problemKey !== null) {
            $state->report(
                Message::warning($translator->translate($startup->problemKey, ['module' => $startup->requested])),
                microtime(true),
            );
        }

        return $startup->screen;
    }

    /**
     * Dopuszczalne wartości klucza `startupModule`: moduły przyjęte, które
     * naprawdę wnoszą ekran. Lista powstaje przy starcie, bo w czasie pisania
     * kodu nikt jej nie zna — i to jest jedyna nowość tego klucza wobec `Theme`
     * i `Language`.
     *
     * @return list<string>
     */
    private static function startupModules(ModuleRegistry $modules): array
    {
        $ids = [];

        foreach ($modules->accepted() as $module) {
            if ($module instanceof ProvidesScreen) {
                $ids[] = $module->id();
            }
        }

        return $ids;
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
     * najpierw „co jest włączone”, potem ustawienia każdego z osobna. Od kroku 21
     * jego pierwszą pozycją jest moduł domyślny.
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
     * temu, co powiedział o sobie start aplikacji: nieotwartemu katalogowi, uwadze
     * do pliku konfiguracji i niedostępnemu modułowi domyślnemu. Moduł, który
     * odpadł, nie jest pilniejszy od tego, czego użytkownik właśnie nie widzi.
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
                static fn (ModuleRejection $rejection): string => $rejection->id
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
        $change = new ChangeSettingUseCase($settings, $themes, $translator, self::startupModules($modules));
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

    /**
     * Stan pętli powstaje **przed** modułami i nic już o katalogu nie wie.
     *
     * Uwaga do pliku konfiguracji stoi tu jako pierwsza, ale nie jako
     * najważniejsza: moduł przeglądarki, budowany zaraz potem, nadpisze ją
     * komunikatem o nieotwartym katalogu, jeśli będzie miał co powiedzieć. Ta
     * kolejność jest tą samą hierarchią ważności, którą do kroku 20 wymuszało
     * `return` w środku tej metody.
     */
    private static function createInitialState(): LoopState
    {
        $loaded = self::loadSettings();
        $state = new LoopState($loaded->settings);

        if ($loaded->problem !== null) {
            $state->report(Message::warning($loaded->problem), microtime(true));
        }

        return $state;
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
