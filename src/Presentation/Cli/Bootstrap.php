<?php

declare(strict_types=1);

namespace LightManager\Presentation\Cli;

use Closure;
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
use LightManager\Application\Port\ClipboardPort;
use LightManager\Application\Port\FrameRendererPort;
use LightManager\Application\Query\QueryLineParser;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Application\UseCase\ChangeSettingUseCase;
use LightManager\Application\UseCase\LoadSettingsUseCase;
use LightManager\Application\UseCase\RestoreDefaultSettingsUseCase;
use LightManager\Domain\ValueObject\Message;
use LightManager\Domain\ValueObject\RendererMode;
use LightManager\Infrastructure\Config\CommandHistoryService;
use LightManager\Infrastructure\Config\SettingsService;
use LightManager\Infrastructure\Diagnostics\BenchmarkTrack;
use LightManager\Infrastructure\Diagnostics\DumpingFrameRenderer;
use LightManager\Infrastructure\Diagnostics\FrameDumpService;
use LightManager\Infrastructure\Diagnostics\TrackImageGrabbers;
use LightManager\Infrastructure\FileSystem\FileOperationsService;
use LightManager\Infrastructure\FileSystem\FileTransferService;
use LightManager\Infrastructure\FileSystem\XdgTrashService;
use LightManager\Infrastructure\Glfw\GlfwClipboardService;
use LightManager\Infrastructure\Glfw\GlfwInputService;
use LightManager\Infrastructure\Glfw\GlfwViewportService;
use LightManager\Infrastructure\Glfw\GlfwWindowService;
use LightManager\Infrastructure\Glfw\VgContextService;
use LightManager\Infrastructure\I18n\TranslatorService;
use LightManager\Infrastructure\Imagick\ImagePreviewService;
use LightManager\Infrastructure\Process\BackgroundProcessService;
use LightManager\Infrastructure\Rendering\OpenGlFrameRenderer;
use LightManager\Infrastructure\Rendering\RendererService;
use LightManager\Infrastructure\Rendering\ThemeService;
use LightManager\Infrastructure\Terminal\SixelCapabilityService;
use LightManager\Infrastructure\Terminal\TerminalClipboardService;
use LightManager\Infrastructure\Terminal\TerminalService;
use LightManager\Infrastructure\Terminal\TerminalSizeService;
use LightManager\Module\AddressBook\Presentation\AddressBookModule;
use LightManager\Module\Audio\Presentation\AudioModule;
use LightManager\Module\Browser\Presentation\BrowserModule;
use LightManager\Module\Docker\Presentation\DockerModule;
use LightManager\Module\FileInfo\Presentation\FileInfoModule;
use LightManager\Module\Kubernetes\Presentation\KubernetesModule;
use LightManager\Module\Ssh\Presentation\SshModule;
use LightManager\Presentation\Cli\Command\ClipboardCommand;
use LightManager\Presentation\Cli\Command\DumpFrameCommand;
use LightManager\Presentation\Cli\Command\FullscreenCommand;
use LightManager\Presentation\Cli\Command\QuitCommand;
use LightManager\Presentation\Cli\Command\ScreenCommand;
use LightManager\Presentation\Cli\Command\SettingCommand;
use LightManager\Presentation\Cli\Query\CoreQueries;
use LightManager\Presentation\Cli\Query\CoreReader;
use LightManager\Presentation\Cli\Screen\HelpScreen;
use LightManager\Presentation\Cli\Screen\SettingsScreen;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\Overlay\CommandOverlay;
use LightManager\Presentation\Ui\Overlay\MenuOverlay;
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
     * Czy aplikacja działa w trybie okienkowym (krok 34). Wybór zapada raz,
     * przy starcie, flagą CLI — i musi przeżyć między `boot()`
     * a `createGameLoop()` i `shutdown()`, więc stoi tu, obok historii,
     * jako drugi i ostatni stan bootstrapu.
     */
    private static bool $windowed = false;

    /**
     * Kolejność jest wymuszona jawnie, bo każda z tych usług ma w konstruktorze
     * efekt uboczny wymagany przed pętlą: tryb surowy terminala, wykrycie trybu
     * renderowania, przejęcie ekranu.
     *
     * Konfiguracja wchodzi przed renderowaniem, bo to ona wybiera motyw — a
     * odczytana po rendererze zostałaby zapamiętana **bez sprawdzenia nazwy
     * palety**, więc plik z literówką w kluczu `theme` przeszedłby bez słowa.
     *
     * Tor okienkowy (krok 34) ma własną sekwencję i **nie dotyka ani jednej
     * usługi terminalowej**: bez trybu surowego, bez zapytania DA1, bez
     * alternatywnego bufora. Konfiguracja wchodzi tu **przed** oknem, bo to
     * z niej pochodzi rozmiar startowy (D53) — a pułapki znanej z toru
     * terminalowego nie ma, bo odczyt pliku terminala nie dotyka.
     */
    public static function boot(bool $windowed = false): void
    {
        self::$windowed = $windowed;

        if ($windowed) {
            self::loadSettings();

            // Okno rodzi się ukryte; komórka siatki wychodzi z metryk fontu
            // (krok 35), więc dopiero z nią da się przeliczyć rozmiar
            // startowy z ustawień na piksele — i pokazać okno raz, w dobrym
            // rozmiarze, zamiast szarpać je na oczach użytkownika.
            GlfwWindowService::getInstance();
            $vg = VgContextService::getInstance();

            $settings = SettingsService::getInstance()->current();
            $window = GlfwWindowService::getInstance();
            $window->showAtGrid(
                $settings->windowColumns,
                $settings->windowRows,
                $vg->cellWidthPixels(),
                $vg->cellHeightPixels(),
            );

            // Dopiero **za** pokazaniem okna (krok 37): od tej chwili rozmiar
            // nadany przez użytkownika wraca przy następnym starcie, a wcześniej
            // nie ma czego pamiętać — rozmiar tymczasowy i ten z ustawień nie są
            // niczyim wyborem podjętym teraz.
            $window->rememberSize($vg->cellWidthPixels(), $vg->cellHeightPixels());

            GlfwInputService::getInstance();

            return;
        }

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

        // Słownik zdarzeń (krok 46): rdzeń wnosi swoje pięć sam, przy budowie
        // rejestru, a ta linia dokłada zdarzenia modułów i zapisuje odbiorców.
        // Wyłączony i odrzucony moduł nie wnosi ani nie słucha niczego — rejestr
        // oddaje **przyjęte**, dokładnie jak przy takcie.
        $state->events()->useModules($modules->accepted());

        // Kolejność od kroku 53 jest wymuszona **w trzech miejscach naraz** i każde
        // z nich wynika z tego samego: rejestr kwerend jest jedyną drogą odczytu,
        // więc musi stać, zanim ktokolwiek zacznie czytać.
        //
        // 1. Rejestr komend powstaje **pusty**, bo kwerenda `core.commands` bierze
        //    go obiektem — komendy dopisane później są w nim widoczne same z siebie.
        // 2. Kwerendy rdzenia wchodzą **przed modułami**, bo moduł składa się
        //    czytając ustawienia (przeglądarka otwiera katalog startowy wedle
        //    klucza `showHiddenEntries`), a czyta je przez `CoreReader`.
        // 3. Kwerendy modułów wchodzą **przed oknami komend**, bo `prepare()`
        //    liczy podpowiedzi stałe raz i już się nie odświeży.
        $registry = new CommandRegistry();
        // Stan pętli **podaje** rejestr komend modułom (krok 54): bez tego moduł
        // zna nazwę cudzej czynności i nie ma czego o nią zapytać. Podanie idzie
        // **przed** składaniem modułów, bo `k8s.deploy-image` bierze rejestr
        // w konstruktorze — a wypełnia się on dopiero niżej i to jest w porządku,
        // bo trzymamy **obiekt**, nie jego zawartość.
        $state->useCommands($registry);
        self::registerQueries($state, $modules, $registry);
        $state->queries()->useModules($modules->accepted());

        [$commands, $menu] = self::createCommandWindows($state, $settings, $translator, $modules, $registry);
        $commands->prepare();

        $settingsScreen = new SettingsScreen(
            $state,
            new CoreReader($state->queries()),
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

        // W torze okienkowym o tryb nie pyta się detektora: wybór wyprzedził
        // detekcję i DA1 nie zostało wysłane, bo nie było go do kogo wysłać.
        $help = new HelpScreen(
            $settings,
            $translator,
            self::VERSION,
            self::rendererMode()->name,
            self::contentScale($translator),
        );

        $floor = self::floor($modules, $state, $translator);
        $fullscreen = self::fullscreenToggle();

        // Pomoc składa spis klawiszy z wiązań, więc musi poznać pozostałe ekrany
        // — wraz ze sobą, bo własne klawisze też są częścią spisu — oraz oba okna
        // rejestru komend, które ekranami nie są, a klawisze mają. Ekrany modułów
        // idą przez `knowAboutModules()`, bo dostają własne zakładki.
        $help->knowAbout(
            [$settingsScreen, $help],
            InputHandler::globalBindings(self::$windowed),
            [
                'layout.zone.command' => $commands->bindings(),
                'menu.title' => $menu->bindings(),
            ],
        );
        $help->knowAboutModules($modules->accepted());

        $screens = new ScreenStack($floor);

        self::reportModuleProblems($modules, $state, $translator);

        $input = new InputHandler(
            $screens,
            $help,
            $settingsScreen,
            self::problemPresenter(),
            $translator,
            $commands,
            self::moduleScreens($modules),
            $fullscreen,
            $menu,
            self::clipboard(),
        );

        // Komendy schowka wchodzą do rejestru **po** rozdzielaczu wejścia, a nie
        // razem z pozostałymi komendami rdzenia, i jest to kolejność wymuszona:
        // obie wracają do `InputHandler`a udając naciśnięcie (krok 57), a ten
        // powstaje dopiero tutaj, bo bierze w konstruktorze oba okna rejestru.
        // Późna rejestracja niczego nie psuje — rejestr dokłada i sortuje na
        // nowo, a `prepare()` liczy podpowiedzi **argumentów**, których te dwie
        // komendy nie mają.
        $registry->add(CommandRegistry::CORE, [
            ClipboardCommand::copy($input, $state, $translator),
            ClipboardCommand::paste($input, $state, $translator),
        ]);

        // Jedyne miejsce, w którym tory się różnią: te same trzy porty, inne
        // implementacje. Pętla, ekrany, moduły i komponenty nie wiedzą,
        // że cokolwiek się zmieniło — to jest miara powodzenia kroku 34.
        return new GameLoop(
            self::$windowed ? GlfwInputService::getInstance() : TerminalService::getInstance(),
            new FrameComposer(
                self::dumpingRenderer(
                    self::$windowed ? new OpenGlFrameRenderer() : RendererService::getInstance(),
                ),
                self::$windowed ? GlfwViewportService::getInstance() : TerminalSizeService::getInstance(),
                $translator,
                // Stopka dostaje **więcej** niż okno pomocy: do klawiszy rdzenia
                // dochodzą skróty modułów, których `globalBindings()` nie zna, bo
                // powstają dopiero tutaj — z rejestru. W pomocy stoją w zakładce
                // swojego modułu, więc drugi raz ich tam nie ma (krok 40).
                [
                    ...InputHandler::globalBindings(self::$windowed),
                    ...InputHandler::moduleBindings($modules->shortcuts()),
                ],
            ),
            $screens,
            $input,
            $state,
            // Takt modułów (krok 45). Rejestr oddaje **przyjęte** moduły, więc
            // wyłączony i odrzucony taktu nie dostaje — a odsiew tych, które
            // o niego proszą, zdarza się tu raz, nie trzydzieści razy na sekundę.
            ModuleTicker::of($modules->accepted(), self::problemPresenter()),
            // Pompowanie potoków prac tłowych (krok 51). Ta sama usługa, którą
            // moduły znają jako `BackgroundProcessPort` — ale pod drugim portem,
            // bo pompowanie należy do pętli, a nie do modułu.
            BackgroundProcessService::getInstance(),
        );
    }

    /**
     * Przełącznik pełnego ekranu — domknięcie albo `null` poza torem okienkowym
     * (krok 37).
     *
     * Jedno domknięcie obsługuje obie drogi: komendę `core.fullscreen` i skrót
     * `F11`. Dzięki niemu ani komenda, ani `InputHandler` nie znają
     * `Infrastructure/Glfw` — nazwę usługi okna wymienia wyłącznie ta klasa,
     * dokładnie tak samo jak przy pozostałych portach toru okienkowego.
     *
     * @return ?Closure(): bool
     */
    /**
     * Schowek środowiska graficznego — **ta sama para portów, inne
     * implementacje**, jak przy wejściu, widoku i rendererze (krok 57).
     *
     * Ta klasa jest jedynym miejscem wymieniającym obie nazwy usług, dokładnie
     * tak samo jak przy `fullscreenToggle()` poniżej: `InputHandler` zna wyłącznie
     * `ClipboardPort`, więc nie wie, czy treść idzie sekwencją `OSC 52`, czy
     * wywołaniem GLFW — i nie ma powodu wiedzieć.
     */
    private static function clipboard(): ClipboardPort
    {
        return self::$windowed
            ? GlfwClipboardService::getInstance()
            : TerminalClipboardService::getInstance();
    }

    private static function fullscreenToggle(): ?Closure
    {
        if (!self::$windowed) {
            return null;
        }

        return static fn (): bool => GlfwWindowService::getInstance()->toggleFullscreen();
    }

    /**
     * Gęstość wyświetlacza gotowa do pokazania w oknie pomocy albo `null`, gdy
     * nie ma jej kto zmierzyć (krok 37, rozstrzygnięcie nr 4).
     *
     * Wartość jest **czytana i pokazywana, a nie stosowana**: maszyna projektu
     * ma skalę 1.0, więc przeliczanie komórki byłoby kodem bez sprawdzenia.
     * Osie rozdzielamy, bo `glfwGetWindowContentScale` oddaje dwie liczby —
     * a wyświetlacz, na którym się różnią, jest właśnie tym, o czym chcielibyśmy
     * usłyszeć.
     */
    private static function contentScale(TranslatorService $translator): ?string
    {
        if (!self::$windowed) {
            return null;
        }

        $scale = GlfwWindowService::getInstance()->contentScale();

        return $translator->number($scale['x'], 2) . ' × ' . $translator->number($scale['y'], 2);
    }

    /**
     * Renderer opakowany zamówieniem zrzutu klatki (krok 38, komenda
     * `core.dump`).
     *
     * Dekorator zamiast zmiany w `FrameComposer`: w ścieżce klatki zostaje
     * sprawdzenie jednego pola, a składanie klatki jest nietknięte. Sposób
     * oddania obrazu wybiera **`Bootstrap`, bo tylko on wie, który tor został
     * wybrany** — usługa zrzutu nie ma prawa tego zgadywać, a zrzut z cudzego
     * toru nie byłby dowodem na nic.
     */
    private static function dumpingRenderer(FrameRendererPort $renderer): FrameRendererPort
    {
        $dumps = FrameDumpService::getInstance();
        $dumps->useGrabber(TrackImageGrabbers::forTrack(self::dumpTrack()));

        return new DumpingFrameRenderer($renderer, $dumps);
    }

    /** Tor, którym idzie klatka tego uruchomienia — okno, Sixel albo tekst. */
    private static function dumpTrack(): BenchmarkTrack
    {
        if (self::$windowed) {
            return BenchmarkTrack::Window;
        }

        return SixelCapabilityService::getInstance()->detect() === RendererMode::Sixel
            ? BenchmarkTrack::Sixel
            : BenchmarkTrack::Text;
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
            // Porty zapisu są **piątą, szóstą i siódmą rzeczą rdzenia**, którą
            // dostaje przeglądarka (kroki 41, 42 i 44): usługi pisania po dysku
            // mieszkają w rdzeniu jako wspólne, bo drugim ich odbiorcą jest moduł
            // opisu pliku, a moduł nigdy nie sięga do innego modułu (D66, jawny
            // wyjątek od reguły 15). Porty są trzy, bo czynność natychmiastowa,
            // praca kawałkowa i kosz mają zupełnie inny stan (D79 nr 1; D81).
            new BrowserModule(
                $state,
                $translator,
                $settings,
                FileOperationsService::getInstance(),
                FileTransferService::getInstance(),
                XdgTrashService::getInstance(),
            ),
            new FileInfoModule(
                $state,
                $translator,
                $settings,
                ImagePreviewService::getInstance(),
                BackgroundProcessService::getInstance(),
            ),
            // Trzecia pozycja i **cały koszt modułu dźwięku w rdzeniu** (krok 36).
            // Rdzeń nie wie o nim nic ponad to: ani że gra, ani czym.
            new AudioModule($state, $translator, $settings),
            // Czwarta pozycja i **cały koszt książki adresowej w rdzeniu**
            // (krok 60). Stoi **przed** modułami, które ją czytają, i jest to
            // porządek dla oka, nie warunek: rejestr kwerend wypełnia się po
            // zbudowaniu rejestru modułów, więc kolejność deklaracji nie
            // rozstrzyga o niczym poza układem zakładek. Moduł nie odmawia
            // startu nigdy — odrzucony zabrałby adresy wszystkim pozostałym.
            new AddressBookModule($state, $translator, $settings),
            // Piąta pozycja i **cały koszt modułu sesji zdalnej w rdzeniu**
            // (krok 48). Rdzeń nie wie o nim nic ponad to — ani z czym rozmawia,
            // ani że rozmawia w procesie potomnym. Moduł bywa przy tym pierwszym,
            // który się tu nie zjawi: bez klienta OpenSSH rejestr go odrzuca
            // (`RequiresEnvironment`, D87 nr 11), a ta linia zostaje ta sama.
            new SshModule($state, $translator, $settings),
            // Szósta pozycja i **cały koszt modułu Dockera w rdzeniu** (krok 51)
            // ponad rozbudowę portu pracy tłowej, która jest osobnym zakresem
            // tego samego kroku i ma trzech odbiorców, nie jednego. Rdzeń nie
            // wie o tym module nic ponad tę linię — ani że rozmawia gniazdem,
            // ani że compose idzie procesem potomnym. Bez `ext-curl` albo bez
            // gniazda demona rejestr go odrzuca (`RequiresEnvironment`, D90 nr 6),
            // a ta linia zostaje ta sama.
            new DockerModule($state, $translator, $settings),
            // Siódma pozycja i **cały koszt modułu klastra w rdzeniu** (krok 52)
            // ponad rozbudowę portu pracy tłowej o wypis pracy trwającej
            // (D91 nr 12) — tamta ma własnego odbiorcę w logach `kubectl -f`
            // i własne testy. Rdzeń nie wie o tym module nic ponad tę linię:
            // ani że rodzaje zasobów pochodzą z klastra, ani że jedno z jego
            // wywołań rozczytuje tekst. Bez klienta `kubectl` rejestr go
            // odrzuca (`RequiresEnvironment`, reguła 11s), a ta linia zostaje
            // ta sama.
            new KubernetesModule($state, $translator, $settings),
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

    /**
     * Napisy modułów wchodzą do katalogu przed pierwszym tłumaczeniem.
     *
     * **Wszystkich zadeklarowanych, nie tylko przyjętych** — poprawka z kroku 48.
     * Spis na zakładce „Moduły" idzie po `declared()`, więc tłumaczy nazwę
     * i powód odrzucenia także temu, kto nie wszedł; przy `accepted()` wypisywał
     * w tych dwóch miejscach surowe klucze. Do kroku 48 nikt tego nie widział,
     * bo wszystkie cztery powody odrzucenia były błędami autora modułu i w wydanej
     * aplikacji nie zdarzały się nigdy. Piąty powód (`RequiresEnvironment`) zależy
     * od maszyny użytkownika, więc usterka przestała być teoretyczna.
     */
    private static function registerTranslations(ModuleRegistry $modules, TranslatorService $translator): void
    {
        foreach ($modules->declared() as $module) {
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
     * Oba okna rejestru komend: okno komend wraz z parserem i historią oraz menu
     * kontekstowe (krok 32).
     *
     * Powstają w jednym miejscu, bo **dzielą rejestr** — i to jest cały sens
     * kroku 32: menu jest drugim wejściem do tego samego zbioru czynności, a nie
     * drugim zbiorem. Rejestr zbudowany dwa razy byłby dwoma zbiorami niezależnie
     * od tego, że powstałyby z tej samej listy.
     *
     * Kolejność jest wymuszona: rejestr musi znać komplet komend, zanim okno
     * poprosi o **podpowiedzi stałe** (`prepare()`), bo te liczą się raz i już
     * się nie odświeżą. Komendy zmieniające ustawienia dostają stan pętli, żeby
     * zmiana obowiązywała od następnej klatki, a nie od następnego uruchomienia.
     *
     * @return array{CommandOverlay, MenuOverlay}
     */
    private static function createCommandWindows(
        LoopState $state,
        SettingsService $settings,
        TranslatorService $translator,
        ModuleRegistry $modules,
        CommandRegistry $registry,
    ): array {
        $themes = ThemeService::getInstance();
        $change = new ChangeSettingUseCase($settings, $themes, $translator, self::startupModules($modules));

        $core = [
            new ScreenCommand('core.help', 'help'),
            new ScreenCommand('core.settings', 'settings'),
            new SettingCommand('core.theme', SettingKey::Theme, 'theme', $themes->names(), $state, $change),
            new SettingCommand('core.language', SettingKey::Language, 'language', self::languageCodes(), $state, $change),
            new DumpFrameCommand($translator),
            new QuitCommand(),
        ];

        // Pełny ekran wchodzi do spisu **wyłącznie w torze okienkowym** (krok 37):
        // w terminalu nie znaczy nic, a okno komend ma pokazywać to, co działa tu
        // i teraz. To pierwsza komenda rdzenia, której obecność zależy od trybu.
        $fullscreen = self::fullscreenToggle();

        if ($fullscreen !== null) {
            $core[] = new FullscreenCommand($translator, $fullscreen);
        }

        $registry->add(CommandRegistry::CORE, $core);

        // Komendy modułu wchodzą pod jego własną przestrzenią nazw — nazwy spoza
        // niej odsiewa rejestr, dokładnie tak samo, jak katalog napisów odsiewa
        // klucze spoza przedrostka modułu.
        foreach ($modules->accepted() as $module) {
            if ($module instanceof ProvidesCommands) {
                $registry->add($module->id(), $module->commands());
            }
        }

        self::$history = new CommandHistory(CommandHistoryService::getInstance());

        // Oba okna wykonują komendę tą samą linią, więc oba ogłaszają to samo
        // zdarzenie (krok 46) — i biorą rejestr z tego samego miejsca, co
        // wszyscy pozostali publikujący.
        $lines = new CommandLineParser($translator);
        $overlay = new CommandOverlay(
            $registry,
            $lines,
            self::$history,
            $translator,
            $state->events(),
            $state->queries(),
            new QueryLineParser($lines, $translator),
        );

        // `prepare()` **nie pada tutaj** od kroku 53: podpowiedzi stałe liczą się
        // raz i już się nie odświeżą, a rejestr kwerend jest w tej chwili pusty —
        // wypełnia go `registerQueries()` linijkę dalej. Wywołanie zostało więc
        // przeniesione za nie, do `createGameLoop()`.
        return [$overlay, new MenuOverlay($registry, $translator, $state->events())];
    }

    /**
     * Trzynaście źródeł danych rdzenia (krok 53).
     *
     * Rejestr komend przychodzi tu **pusty** i to jest w porządku: kwerenda
     * `core.commands` trzyma go obiektem, a spis czyta dopiero przy pytaniu —
     * tak samo `core.queries`, dopisywana do rejestru, który właśnie wypełnia.
     * Moduły wchodzą osobną linią w `createGameLoop()`, wzorem
     * `EventRegistry::useModules()`: dopisanie kwerend do modułu kosztuje
     * w rdzeniu zero.
     */
    private static function registerQueries(
        LoopState $state,
        ModuleRegistry $modules,
        CommandRegistry $commands,
    ): void {
        $queries = $state->queries();

        $queries->add(QueryRegistry::CORE, CoreQueries::all(
            $state,
            $modules,
            $commands,
            ThemeService::getInstance(),
            self::$windowed ? GlfwViewportService::getInstance() : TerminalSizeService::getInstance(),
            self::rendererMode(),
            self::VERSION,
        ));

    }

    /**
     * Tor, którym idzie klatka — bez pytania detektora w trybie okienkowym, bo
     * wybór wyprzedził detekcję i DA1 nie zostało wysłane.
     */
    private static function rendererMode(): RendererMode
    {
        return self::$windowed ? RendererMode::OpenGl : SixelCapabilityService::getInstance()->detect();
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
     *
     * Proces tłowy ubijamy **przed** jednym i drugim (krok 26): to jedyny byt
     * w tej aplikacji, który przeżyłby proces macierzysty, a `du` puszczone na
     * katalog domowy potrafi chodzić jeszcze długo po tym, jak użytkownik
     * zobaczył z powrotem swoją powłokę. Usługa trzyma dla tej samej sprawy także
     * funkcję zamknięcia procesu — wywołanie tutaj jest jawną ścieżką, tamta
     * gwarancją ostatniej szansy dla wyjść, których ta ścieżka nie dosięga.
     */
    public static function shutdown(): void
    {
        BackgroundProcessService::getInstance()->shutdown();

        self::$history?->flush();

        // Przywracanie terminala zamienia się w zamknięcie okna (krok 34) —
        // i tak samo jak ono jest zdublowane funkcją zamknięcia procesu
        // rejestrowaną w konstruktorze usługi, na wyjścia, których ta ścieżka
        // nie dosięga.
        if (self::$windowed) {
            // Rozmiar nadany oknu w ostatniej pół sekundzie nie zdążył się
            // uspokoić, a jest równie prawdziwym wyborem, co każdy wcześniejszy
            // (krok 37). Zapis idzie **przed** zamknięciem okna, bo po nim nie ma
            // już czego zmierzyć.
            GlfwWindowService::getInstance()->saveSizeIfPending();
            GlfwWindowService::getInstance()->close();

            return;
        }

        TerminalService::getInstance()->restore();
    }
}
