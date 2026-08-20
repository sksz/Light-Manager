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
use LightManager\Application\Port\FileOperationsPort;
use LightManager\Application\Port\FileTransferPort;
use LightManager\Application\Port\TrashPort;
use LightManager\Application\Query\QueryLineParser;
use LightManager\Application\Query\QueryRegistry;
use LightManager\Application\Ui\Rect;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Application\UseCase\ChangeSettingUseCase;
use LightManager\Application\UseCase\RestoreDefaultSettingsUseCase;
use LightManager\Domain\ValueObject\RendererMode;
use LightManager\Module\AddressBook\Presentation\AddressBookModule;
use LightManager\Module\Audio\Presentation\AudioModule;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Module\Browser\Domain\Repository\DirectoryRepositoryInterface;
use LightManager\Module\Browser\Presentation\BrowserModule;
use LightManager\Module\Docker\Presentation\DockerModule;
use LightManager\Module\FileInfo\Presentation\FileInfoModule;
use LightManager\Module\Kubernetes\Presentation\KubernetesModule;
use LightManager\Module\Ssh\Presentation\SshModule;
use LightManager\Presentation\Cli\Bootstrap;
use LightManager\Presentation\Cli\Command\QuitCommand;
use LightManager\Presentation\Cli\Command\ScreenCommand;
use LightManager\Presentation\Cli\Command\SettingCommand;
use LightManager\Presentation\Cli\InputHandler;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Cli\ModuleTicker;
use LightManager\Presentation\Cli\ProblemPresenter;
use LightManager\Presentation\Cli\Query\CoreQueries;
use LightManager\Presentation\Cli\Query\CoreReader;
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

    /** Okno playlisty — moduł dźwięku ma je od kroku 45, wraz z taktem. */
    public readonly ScreenInterface $audioScreen;

    /** Takt modułów: to samo wołanie, które w aplikacji robi `GameLoop`. */
    public readonly ModuleTicker $ticker;

    /** Spis hostów modułu sesji zdalnej (krok 48). */
    public readonly ScreenInterface $sshScreen;

    /** Ekran modułu Dockera (krok 51) — kontenery, obrazy i logi w jednym. */
    public readonly ScreenInterface $dockerScreen;

    /** Ekran modułu klastra (krok 52) — drzewo rodzajów i treść obok niego. */
    public readonly ScreenInterface $kubernetesScreen;

    /** Ekran książki adresowej (krok 60) — zakładki rozdziałów nad tabelą wpisów. */
    public readonly ScreenInterface $addressBookScreen;

    public readonly CommandRegistry $commandRegistry;

    /** Ekran, który stanął na dnie stosu — i powód, gdy nie ten, o który proszono. */
    public readonly StartupScreen $startup;

    /**
     * @param DirectoryRepositoryInterface $directories źródło katalogów: w pamięci
     *                                                 albo — dla przebiegów
     *                                                 sprawdzających operacje na
     *                                                 plikach — prawdziwy system
     *                                                 plików w katalogu tymczasowym
     * @param FileOperationsPort           $operations  czynności zmieniające dysk;
     *                                                 domyślnie atrapa, bo ścieżki
     *                                                 katalogów w pamięci bywają
     *                                                 prawdziwe na maszynie testowej
     */
    public function __construct(
        Directory $directory,
        public readonly DirectoryRepositoryInterface $directories,
        public readonly InMemorySettings $settingsStore = new InMemorySettings(),
        public readonly StubFileInspector $inspector = new StubFileInspector('PDF document, version 1.7'),
        public readonly InMemoryCommandHistory $history = new InMemoryCommandHistory(),
        public readonly StubFileStat $stats = new StubFileStat(),
        public readonly StubChecksums $checksums = new StubChecksums(),
        public readonly StubBackgroundProcess $processes = new StubBackgroundProcess(),
        public readonly FileOperationsPort $operations = new StubFileOperations(),
        public readonly FileTransferPort $transfers = new StubFileTransfers(),
        public readonly TrashPort $trash = new StubTrash(),
        public readonly StubAudio $audio = new StubAudio(),
        public readonly StubPlaylistStorage $playlist = new StubPlaylistStorage(),
        public readonly StubTrackFiles $tracks = new StubTrackFiles(),
        public readonly StubEffectStorage $effects = new StubEffectStorage(),
        public readonly StubSshSession $sessions = new StubSshSession(),
        public readonly StubSshState $sshState = new StubSshState(),
        public readonly StubRemoteDirectory $remote = new StubRemoteDirectory(),
        public readonly StubRemoteTransfer $remoteTransfers = new StubRemoteTransfer(),
        public readonly StubDockerApi $docker = new StubDockerApi(),
        public readonly StubCompose $compose = new StubCompose(),
        public readonly StubDockerState $dockerState = new StubDockerState(),
        public readonly StubContextCatalog $contexts = new StubContextCatalog(),
        public readonly StubTunnel $tunnel = new StubTunnel(),
        public readonly StubKubectl $kubectl = new StubKubectl(),
        /** Sekcja `k8s` w pamięci (krok 60) — dokumentu stanu maszyny test nie dotyka. */
        public readonly StubKubernetesState $kubernetesState = new StubKubernetesState(),
        public readonly StubClipboard $clipboard = new StubClipboard(),
        /** Rozmowa z rejestrem obrazów atrapą — żaden test nie pyta prawdziwego (krok 61). */
        public readonly StubRegistryApi $registryApi = new StubRegistryApi(),
        /** Wpisy książki w pamięci (krok 60) — dokumentu stanu maszyny test nie dotyka. */
        public readonly StubAddressBook $addressBook = new StubAddressBook(),
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
        // Repozytorium na prawdziwym systemie plików zna go już z dysku — dopisanie
        // dotyczy wyłącznie drzewa trzymanego w pamięci (krok 41).
        if ($directories instanceof InMemoryDirectoryRepository) {
            $directories->add($directory->path()->value, $directory->entries());
        }

        $browser = new BrowserModule(
            $this->state,
            $translator,
            $settingsStore,
            $operations,
            $transfers,
            $trash,
            $directories,
            $directory->path(),
        );

        // Moduł dźwięku wchodzi tu z atrapami wszystkich trzech portów: silnika
        // (bo test nie ma prawa zagrać), nośnika playlisty (bo nie ma prawa
        // dotknąć katalogu domowego) i plików utworów (bo nie ma prawa przeglądać
        // dysku). Od kroku 45 wnosi ekran i takt, więc bez niego zestaw przestałby
        // odpowiadać `Bootstrapowi` w rzeczy, którą ten krok wprowadził.
        $audioModule = new AudioModule(
            $this->state,
            $translator,
            $settingsStore,
            $audio,
            $playlist,
            $tracks,
            $effects,
        );
        $this->audioScreen = $audioModule->screen();

        // Moduł sesji zdalnej wchodzi z atrapami **wszystkich trzech** portów —
        // sesji i odczytu katalogu (bo test nie ma prawa wyjść do sieci) oraz
        // książki hostów (bo nie ma prawa dotknąć katalogu domowego). Podstawiony
        // port sesji jest zarazem odpowiedzią na `RequiresEnvironment`: bez niego
        // zestaw modułów zależałby od tego, czy maszyna uruchamiająca testy ma
        // zainstalowanego klienta OpenSSH (krok 48).
        //
        // **Od kroku 60 trzeci port nie niesie już książki**, tylko sekcję stanu
        // tego modułu: wpisy przyszły do wspólnego rejestru, a atrapa zostaje
        // z tego samego powodu, co była — dokument stanu maszyny testowej należy
        // do użytkownika.
        //
        // **Trzeci port doszedł w kroku 49 po tym, jak jego brak wypuścił z testu
        // prawdziwe procesy `sftp`** do hosta z przykładowego wpisu książki.
        // Podstawiona sesja mówiła „połączono", więc ekran zamawiał odczyt
        // katalogu — a odczyt bez atrapy szedł prawdziwą usługą.
        // **Czwarty port doszedł w kroku 50 z tego samego powodu, co trzeci** —
        // i stawka jest tu wyższa: przesył nie tylko wychodzi do sieci, ale
        // **pisze po dysku**, więc przebieg bez atrapy zostawiałby po sobie pliki
        // w katalogu, w którym akurat stoi test.
        $sshModule = new SshModule(
            $this->state,
            $translator,
            $settingsStore,
            $sessions,
            $sshState,
            $remote,
            $remoteTransfers,
        );
        $this->sshScreen = $sshModule->screen();

        // Moduł Dockera wchodzi z atrapami obu portów — gniazda demona (bo test
        // nie ma prawa zatrzymać cudzego kontenera ani skasować cudzego obrazu)
        // i wtyczki compose (bo `up` sięga po obrazy do sieci). Podstawiony port
        // gniazda jest zarazem odpowiedzią na `RequiresEnvironment`: bez niego
        // zestaw modułów zależałby od tego, czy maszyna uruchamiająca testy ma
        // zainstalowanego Dockera (krok 51).
        //
        // **Trzy porty środowisk doszły w kroku 58 z tych samych powodów, co
        // trzeci port modułu Ssh w kroku 49**: książka bez atrapy czytałaby
        // `~/.light-manager/docker.json` maszyny testowej, odczyt kontekstów
        // uruchamiałby prawdziwy proces `docker context ls`, a tunel —
        // prawdziwy `ssh` do hosta z przykładowego wpisu.
        $dockerModule = new DockerModule(
            $this->state,
            $translator,
            $settingsStore,
            $docker,
            $compose,
            $dockerState,
            $contexts,
            $tunnel,
            $registryApi,
        );
        $this->dockerScreen = $dockerModule->screen();

        // Moduł klastra wchodzi z atrapą klienta `kubectl` (krok 52). Powód jest
        // ten sam, co przy Dockerze, tylko ostrzejszy: **kryterium ukończenia
        // kroku brzmi „żaden test nie wywołuje `kubectl`”**, a bez podstawionego
        // portu przebieg zależałby od tego, czy maszyna testująca ma klienta —
        // i czy akurat wskazuje na czyjś klaster.
        $kubernetesModule = new KubernetesModule($this->state, $translator, $settingsStore, $kubectl, $kubernetesState);
        $this->kubernetesScreen = $kubernetesModule->screen();

        // Książka adresowa wchodzi z atrapą swojej sekcji dokumentu stanu —
        // z tego samego powodu, co książki hostów, środowisk i klastrów: test nie
        // ma prawa czytać ani pisać `~/.light-manager/state.json` maszyny, na
        // której akurat biegnie. **Portu środowiska nie ma i mieć nie będzie**:
        // książka nie deklaruje `RequiresEnvironment`, bo odrzucona zabrałaby
        // wpisy wszystkim pozostałym modułom (krok 60).
        $addressBookModule = new AddressBookModule($this->state, $translator, $addressBook);
        $this->addressBookScreen = $addressBookModule->screen();

        $this->modules = new ModuleRegistry(
            [$browser, $fileInfo, $audioModule, $sshModule, $dockerModule, $kubernetesModule, $addressBookModule],
            $settingsStore->current()->modules,
            Bootstrap::LAST_RESORT_MODULE,
        );

        // Słownik zdarzeń i odbiorcy — ta sama jedna linia, co w `Bootstrapie`
        // (krok 46). Bez niej przebieg sprawdzałby aplikację, w której nikt
        // niczego nie ogłasza, czyli nie tę, którą uruchamia użytkownik.
        $this->state->events()->useModules($this->modules->accepted());

        $this->ticker = ModuleTicker::of($this->modules->accepted(), new ProblemPresenter($translator));

        $this->browser = $browser->screen();

        $this->settings = new SettingsScreen(
            $this->state,
            new CoreReader($this->state->queries()),
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
        // Stan pętli podaje rejestr komend modułom (krok 54) — **przed** pytaniem
        // ich o komendy, bo `k8s.deploy-image` bierze go w konstruktorze.
        // To jest ta sama kolejność, którą wymusza `Bootstrap`.
        $this->state->useCommands($this->commandRegistry);
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

        // Kwerendy rdzenia (krok 53) — z **tego samego** spisu, co w aplikacji:
        // dwa wyliczenia rozjechałyby się przy pierwszej dołożonej pozycji,
        // a test przechodziłby na spisie starszym o jedną kwerendę.
        $this->state->queries()->add(QueryRegistry::CORE, CoreQueries::all(
            $this->state,
            $this->modules,
            $this->commandRegistry,
            $themes,
            new FixedViewport(),
            RendererMode::Sixel,
            Bootstrap::VERSION,
        ));

        // Kwerendy modułów (krok 53) — tą samą jedną linią, co w `Bootstrapie`.
        // Bez niej ekran modułu nie ma jak przeczytać własnych danych, bo odkąd
        // rejestr jest jedyną drogą odczytu, moduł niezarejestrowany jest modułem
        // niewidzącym.
        $this->state->queries()->useModules($this->modules->accepted());

        $lines = new CommandLineParser($translator);
        $this->commands = new CommandOverlay(
            $this->commandRegistry,
            $lines,
            new CommandHistory($history),
            $translator,
            $this->state->events(),
            $this->state->queries(),
            new QueryLineParser($lines, $translator),
        );
        $this->commands->prepare();
        $this->menu = new MenuOverlay($this->commandRegistry, $translator, $this->state->events());

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
            $translator,
            $this->commands,
            self::moduleScreens($this->modules),
            null,
            $this->menu,
            // Schowek **wyłącznie atrapą** (krok 57): obie prawdziwe
            // implementacje piszą po schowku osoby uruchamiającej testy.
            $this->clipboard,
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
