<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Module\DeclaresEvents;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleSettingsTab;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\NeedsTick;
use LightManager\Application\Module\ProvidesCommands;
use LightManager\Application\Module\ProvidesQueries;
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Module\RequiresEnvironment;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Module\Docker\Application\BuildWork;
use LightManager\Module\Docker\Application\ContainerList;
use LightManager\Module\Docker\Application\DockerEvent;
use LightManager\Module\Docker\Application\DockerSettings;
use LightManager\Module\Docker\Application\Environments;
use LightManager\Module\Docker\Application\ImageList;
use LightManager\Module\Docker\Application\LogStream;
use LightManager\Module\Docker\Application\Port\ComposePort;
use LightManager\Module\Docker\Application\Port\ContextCatalogPort;
use LightManager\Module\Docker\Application\Port\DockerApiPort;
use LightManager\Module\Docker\Application\Port\DockerStatePort;
use LightManager\Module\Docker\Application\Port\RegistryPort;
use LightManager\Module\Docker\Application\Port\TunnelPort;
use LightManager\Module\Docker\Application\PullWork;
use LightManager\Module\Docker\Application\PushWork;
use LightManager\Module\Docker\Application\Registries;
use LightManager\Module\Docker\Application\RegistryBrowse;
use LightManager\Module\Docker\Infrastructure\BuildContextPacker;
use LightManager\Module\Docker\Infrastructure\BuildProgressReader;
use LightManager\Module\Docker\Infrastructure\ComposeCliService;
use LightManager\Module\Docker\Infrastructure\DockerApiService;
use LightManager\Module\Docker\Infrastructure\DockerContextReader;
use LightManager\Module\Docker\Infrastructure\DockerJsonReader;
use LightManager\Module\Docker\Infrastructure\DockerStateService;
use LightManager\Module\Docker\Infrastructure\LogFrameReader;
use LightManager\Module\Docker\Infrastructure\RegistryApiService;
use LightManager\Module\Docker\Infrastructure\SocketTunnelService;
use LightManager\Module\Docker\Presentation\Command\BuildCommand;
use LightManager\Module\Docker\Presentation\Command\ComposeDownCommand;
use LightManager\Module\Docker\Presentation\Command\ComposeUpCommand;
use LightManager\Module\Docker\Presentation\Command\ImagesCommand;
use LightManager\Module\Docker\Presentation\Command\PsCommand;
use LightManager\Module\Docker\Presentation\Command\PullCommand;
use LightManager\Module\Docker\Presentation\Command\PushCommand;
use LightManager\Module\Docker\Presentation\Query\BuildQuery;
use LightManager\Module\Docker\Presentation\Query\CatalogQuery;
use LightManager\Module\Docker\Presentation\Query\ComposeQuery;
use LightManager\Module\Docker\Presentation\Query\ContainersQuery;
use LightManager\Module\Docker\Presentation\Query\EnvironmentsQuery;
use LightManager\Module\Docker\Presentation\Query\ImagesQuery;
use LightManager\Module\Docker\Presentation\Query\PullQuery;
use LightManager\Module\Docker\Presentation\Query\PushQuery;
use LightManager\Module\Docker\Presentation\Query\RegistriesQuery;
use LightManager\Module\Docker\Presentation\Query\RegistrySecretQuery;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Cli\Query\CoreReader;
use LightManager\Presentation\Cli\SplitSetting;
use LightManager\Presentation\Ui\Module\ProvidesHelpTab;
use LightManager\Presentation\Ui\Module\ProvidesScreen;

/**
 * Docker jako moduł (krok 51) — **piąty sprawdzian kontraktu z kroku 20**.
 *
 * Po module rysującym główną funkcję aplikacji (21), module bez ekranu (36),
 * module pracującym, gdy go nie widać (45), i module rozmawiającym z cudzą
 * maszyną (48) przychodzi moduł **prowadzący kilka rozmów naraz**: dwie listy,
 * strumień logów, budowa i praca compose potrafią trwać w tej samej chwili.
 *
 * Rdzeń kosztuje **jedną linię w `Bootstrapie`** ponad rozbudowę portu pracy
 * tłowej, która jest osobnym zakresem tego samego kroku i ma trzech odbiorców,
 * nie jednego.
 *
 * **Moduł odmawia startu bez `ext-curl` albo bez gniazda** (`RequiresEnvironment`,
 * D90 nr 6), ale **nie odmawia z powodu leżącego demona** — i to rozdzielenie
 * jest świadome: rozszerzenia nie da się doładować w trakcie działania
 * aplikacji, a demona da się podnieść. Moduł odrzucony przy starcie nie wróciłby
 * aż do restartu, więc zatrzymany demon jest zdaniem na ekranie, a nie powodem
 * nieobecności.
 *
 * Składa się leniwie, jak wszystkie: napisy wchodzą do katalogu **po** zbudowaniu
 * rejestru modułów, więc komenda ani ekran zbudowane zachłannie mogłyby wypisać
 * użytkownikowi surowy klucz. Demona **nie pyta o nic**, dopóki ktoś nie otworzy
 * ekranu — uruchomienie aplikacji nie kosztuje ani jednego bajtu na gnieździe.
 */
final class DockerModule implements
    ModuleInterface,
    RequiresEnvironment,
    ProvidesSettingsTab,
    ProvidesCommands,
    ProvidesQueries,
    ProvidesHelpTab,
    ProvidesScreen,
    NeedsTick,
    DeclaresEvents
{
    /**
     * Litera skrótu — `o` jak dOcker (D90 nr 2).
     *
     * Propozycja planu brzmiała `k` („kontenery”) i przegrała jednym argumentem:
     * `k` jest utrwalonym poza tą aplikacją skrótem Kubernetesa (alias `k` dla
     * `kubectl`), więc oddanie go Dockerowi kazałoby krokowi 52 wziąć literę
     * słabszą **i** zaskoczyć użytkownika klastra. Zajęte są `b` (przeglądarka),
     * `d` (opis pliku), `a` (dźwięk) i `s` (sesja zdalna); `t` jest wolne jako
     * litera modułu, ale zajęte w praktyce przez `Ctrl`+`T` przeglądarki.
     */
    private const SHORTCUT = 'o';

    /** Domyślny podział: lista kontenerów i opis po połowie (krok 55). */
    private const SPLIT_PERCENT = 50;

    /** @var list<CommandInterface>|null */
    private ?array $commands = null;

    private ?DockerScreen $screen = null;

    private ?ContainerList $containers = null;

    private ?ImageList $images = null;

    private ?LogStream $logs = null;

    private ?BuildFlow $builds = null;

    /**
     * Budowa — **jedna na moduł**, trzymana tutaj, a nie tylko w łańcuchu okien.
     *
     * Do kroku 54 mieszkała wyłącznie w `BuildFlow`, bo posuwało ją jego okno
     * postępu. Odkąd posuwa ją **takt modułu** (D94 nr 5), potrzebują jej trzy
     * miejsca: takt, łańcuch okien i kwerenda `docker.build` — a trzy obiekty
     * znaczyłyby trzy prawdy o jednej budowie.
     */
    private ?BuildWork $work = null;

    private ?PushWork $pushWork = null;

    private ?PushFlow $pushes = null;

    private ?ComposeFlow $composeFlow = null;

    private ?DockerQueries $reader = null;

    private ?Registries $registries = null;

    private ?RegistryBrowse $browse = null;

    private ?RegistryPane $registryPane = null;

    private ?PullWork $pullWork = null;

    private ?PullFlow $pulls = null;

    /** Środowiska — „z którym demonem" jako dana, jedna na moduł (krok 58). */
    private ?Environments $environments = null;

    private ?DockerChapter $chapter = null;

    private ?EnvironmentScreen $environmentScreen = null;

    /**
     * @param ?DockerApiPort $api  wstrzyknięcie istnieje dla testów, które nie mają
     *                             prawa zapytać demona — tak samo, jak testy dźwięku
     *                             nie mają prawa uruchomić silnika. `null` znaczy
     *                             „weź usługę na gnieździe unixowym”
     * @param ?ComposePort   $compose jw. — wtyczka compose uruchamia proces potomny
     */
    public function __construct(
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $settings,
        private readonly ?DockerApiPort $api = null,
        private readonly ?ComposePort $compose = null,
        /** Trzy porty środowisk — wstrzyknięcie istnieje dla testów, jak `$api` (krok 58). */
        private readonly ?DockerStatePort $dockerState = null,
        private readonly ?ContextCatalogPort $contexts = null,
        private readonly ?TunnelPort $tunnel = null,
        /** Port rozmowy z rejestrem — wstrzyknięcie dla testów, jak `$api` (krok 61). */
        private readonly ?RegistryPort $registryApi = null,
    ) {
    }

    public function id(): string
    {
        return DockerSettings::ID;
    }

    public function nameKey(): string
    {
        return 'module.' . DockerSettings::ID . '.name';
    }

    public function descriptionKey(): string
    {
        return 'module.' . DockerSettings::ID . '.description';
    }

    /**
     * Czego brakuje, żeby moduł miał czym działać — **wyłącznie `ext-curl`**
     * (krok 58).
     *
     * Do tego kroku brak gniazda lokalnego odrzucał cały moduł — a przy
     * środowisku zdalnym byłaby to odmowa bez powodu: maszyna bez demona
     * lokalnego jest dokładnie tą, na której zdalne środowisko ma sens (miara
     * druga planu). Brak gniazda jest odtąd **stanem wpisu** — z tym samym
     * zdaniem, co dawniej, ale w treści ekranu. Precedens z kroku 51 („leżący
     * demon nie odrzuca modułu", D90) rozszerza się na demona nieobecnego.
     *
     * Pytanie nadal pada raz, w ścieżce startu, i kosztuje `extension_loaded()`
     * (reguła 11s). Moduł z podstawionym portem (test) nie pyta o nic.
     */
    public function unavailableReason(): ?string
    {
        if ($this->api !== null) {
            return null;
        }

        return DockerApiService::hasCurl()
            ? null
            : 'module.' . DockerSettings::ID . '.unavailable.curl';
    }

    /** `Ctrl`+`O` otwiera listę kontenerów. */
    public function shortcut(): ModuleShortcut
    {
        return new ModuleShortcut(self::SHORTCUT);
    }

    /** Napisy modułu leżą obok jego kodu, a nie w katalogu rdzenia. */
    public function translations(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lang';
    }

    public function settingsTab(): ModuleSettingsTab
    {
        return new ModuleSettingsTab($this->nameKey(), [
            ...DockerSettings::declarations(),
            SplitSetting::declaration(DockerSettings::ID, self::SPLIT_PERCENT),
        ]);
    }

    public function events(): array
    {
        return DockerEvent::declarations();
    }

    public function commands(): array
    {
        return $this->commands ??= $this->assemble();
    }

    public function screen(): DockerScreen
    {
        return $this->screen ??= new DockerScreen(
            $this->containers(),
            $this->images(),
            $this->logs(),
            $this->compose(),
            $this->builds(),
            $this->translator,
            $this->state,
            $this->reader(),
            new CoreReader($this->state->queries()),
            $this->environmentScreen(),
            $this->registryPane(),
            SplitSetting::state(
                DockerSettings::ID,
                self::SPLIT_PERCENT,
                $this->state,
                new ChangeModuleSettingUseCase($this->settings, $this->translator),
            ),
        );
    }

    /**
     * Rozdział książki — **jeden na moduł** i jedyne miejsce (obok
     * `DockerQueries`), w którym ten moduł wie, że książka adresowa istnieje.
     */
    private function chapter(): DockerChapter
    {
        return $this->chapter ??= new DockerChapter(
            $this->state,
            $this->reader(),
            $this->dockerState ?? DockerStateService::getInstance(),
            new CoreReader($this->state->queries()),
        );
    }

    /**
     * Środowiska — **jedne na moduł**, z tego samego powodu, co lista
     * kontenerów: takt posuwa tunel, ekran pokazuje spis, a kwerenda oddaje
     * odpowiedź — trzy obiekty znaczyłyby trzy prawdy o jednym wyborze.
     */
    private function environments(): Environments
    {
        return $this->environments ??= new Environments(
            $this->dockerState ?? DockerStateService::getInstance(),
            $this->contexts ?? DockerContextReader::getInstance(),
            $this->tunnel ?? SocketTunnelService::getInstance(),
        );
    }

    private function environmentScreen(): EnvironmentScreen
    {
        return $this->environmentScreen ??= new EnvironmentScreen(
            $this->environments(),
            $this->translator,
            $this->reader(),
            $this->state,
        );
    }

    /**
     * Fasada odczytu — **jedna na moduł** (krok 53, D92 nr 3; ten moduł dostał ją
     * w kroku 54).
     *
     * Trzyma sam rejestr, więc wolno ją zbudować **przed** kwerendami, których
     * odpowiedzi rozpakowuje — i to jest cała odpowiedź na pozorny cykl: fasada
     * nie zna kwerend, zna nazwy.
     */
    private function registries(): Registries
    {
        return $this->registries ??= new Registries();
    }

    private function browse(): RegistryBrowse
    {
        return $this->browse ??= new RegistryBrowse(
            $this->registryApi ?? RegistryApiService::getInstance(),
            new DockerJsonReader(),
        );
    }

    private function registryPane(): RegistryPane
    {
        return $this->registryPane ??= new RegistryPane($this->browse(), $this->translator);
    }

    private function reader(): DockerQueries
    {
        return $this->reader ??= new DockerQueries($this->state->queries());
    }

    /**
     * Cztery źródła danych tego modułu — **wraz z parą, na której stoi cała
     * Faza XVIII**.
     *
     * `docker.images` i `docker.build` są tym, czym moduł Kubernetesa wdroży
     * obraz, nie znając Dockera z typu (D85). Reszta wchodzi z tego samego
     * powodu, co w każdym module: **kwerendę dostaje wszystko, co da się
     * przeczytać** (D92 nr 1).
     */
    public function queries(): array
    {
        return [
            new ImagesQuery($this->images(), $this->translator, $this->environments()),
            new ContainersQuery($this->containers(), $this->environments()),
            new ComposeQuery($this->compose(), $this->environments()),
            new BuildQuery($this->work()),
            new PushQuery($this->pushWork()),
            new EnvironmentsQuery($this->environments()),
            new RegistriesQuery($this->registries()),
            new CatalogQuery($this->browse()),
            new PullQuery($this->pullWork()),
            new RegistrySecretQuery($this->registries(), $this->reader()),
        ];
    }

    /**
     * Takt modułu — **posunięcie wszystkiego, co trwa poza klatką**.
     *
     * Warunek z D82 („takt wchodzi wtedy, gdy bez niego funkcja nie istnieje”)
     * jest tu spełniony wprost, i to dwiema drogami naraz. Pierwsza: **strumień
     * nieczytany zatrzymuje nadawcę**, więc logi muszą być pompowane także wtedy,
     * gdy użytkownik zajrzał w ustawienia — inaczej po powrocie zastałby w nich
     * dziurę. Druga: praca compose żyje w procesie potomnym, więc chwili,
     * w której projekt stanął, nie zna żaden klawisz.
     *
     * Kolejność jest istotna: **najpierw pompowanie gniazda**, potem ekran. Bez
     * tego stan oglądany przez ekran byłby stanem sprzed klatki, a odpowiedź
     * demona czekałaby jeden takt dłużej, niż musi.
     */
    public function tick(float $now): void
    {
        // Zapowiedź użycia rozdziału książki — **raz na uruchomienie**, wraz
        // z przeniesieniem starego spisu (krok 60, etap 2). Stoi pierwsza, bo
        // wpisy mają być w książce, zanim koordynator o nie poprosi.
        $this->chapter()->tick();

        // Środowisko idzie **przed pompowaniem**: przełączenie unieważnia
        // wszystko, co przyszło od poprzedniego demona (kryterium kroku 58),
        // a punkt końcowy musi stać, zanim ktokolwiek zada pytanie.
        $environments = $this->environments();

        // Wpisy własne **podaje fasada**, bo mieszkają w cudzej książce, a nie
        // w tym module (krok 60). Koordynator dostaje gotową listę i nie wie,
        // skąd się wzięła — tak samo, jak nie wiedział, że czyta ją z pliku.
        $environments->useEntries($this->reader()->bookEntries());
        $environments->tick();

        // Rejestry tą samą drogą i z **mocniejszego** powodu: `docker.registries`
        // nie ma jak zapytać książki sama, bo kwerenda nie woła kwerendy (11w).
        $registries = $this->registries();
        $registries->useEntries($this->reader()->registries());

        // Rozmowa z rejestrem posuwa się **taktem**, a nie własnym widokiem —
        // inaczej stanęłaby, gdy użytkownik przełączy postać ekranu (krok 54).
        //
        // Token idzie **domknięciem, nie wartością**, i nie jest to ozdoba:
        // ten takt pada trzydzieści razy na sekundę, a odczyt materiału
        // uwierzytelnienia ma paść wyłącznie wtedy, gdy rejestr się zmienił.
        $preferred = $registries->preferred();
        $this->browse()->useRegistry(
            $preferred,
            fn (): string => $preferred === null ? '' : $this->reader()->registryToken($preferred->id),
        );
        $this->browse()->tick();

        // Pobranie posuwa się **taktem**, jak wypchnięcie i budowa (D94 nr 5):
        // praca zmieniająca dysk nie ma prawa dziać się w rysowaniu klatki.
        $this->pulls()->advance();

        if ($environments->takeSwitched()) {
            $this->containers()->forget();
            $this->images()->forget();
            $this->logs()->close();
            $this->work()->stop();
            $this->pushWork()->stop();
            $this->state->events()->publish(DockerEvent::EnvironmentChanged->value);
        }

        $this->api()->useEndpoint($environments->endpoint());
        $this->compose()->useEnvironment($environments->composePrefix());

        $this->api()->pump();
        $this->logs()->useLimit(DockerSettings::logLinesFrom($this->settings->current()));
        // Budowa idzie **tutaj**, a nie w oknie postępu (krok 54, D94 nr 5):
        // trwa minutami, a stos okien ma jedno piętro — więc praca posuwana
        // wyłącznie przez własne okno stawała, gdy cokolwiek innego zajęło
        // ekran. Stąd bierze się „`Esc` przerywa czekanie, nie budowę".
        $this->builds()->advance();
        // Wypychanie idzie tym samym torem, co budowa, i z tego samego powodu:
        // trwa minutami, a czynność `k8s.deploy-image` ogląda je **własnym**
        // oknem, nie oknem Dockera.
        $this->pushes()->advance();
        $this->screen()->tick($now);
    }

    /**
     * Część własna zakładki pomocy — to, czego z deklaracji wyczytać się nie da.
     *
     * Siedem zdań i każde odpowiada na pytanie, które użytkownik zada: czym się
     * to otwiera, skąd biorą się listy, co robi każdy z klawiszy czynności, jak
     * działa budowa, po co zawężenie do projektu i dlaczego logi bywają ucięte.
     */
    public function helpKeys(): array
    {
        return [
            'module.' . DockerSettings::ID . '.help.start',
            'module.' . DockerSettings::ID . '.help.lists',
            'module.' . DockerSettings::ID . '.help.actions',
            'module.' . DockerSettings::ID . '.help.logs',
            'module.' . DockerSettings::ID . '.help.build',
            'module.' . DockerSettings::ID . '.help.compose',
            'module.' . DockerSettings::ID . '.help.environments',
            'module.' . DockerSettings::ID . '.help.refresh',
        ];
    }

    /**
     * Lista kontenerów — **jedna na moduł**.
     *
     * Jedna, bo inaczej takt posuwałby jeden stan, ekran pokazywał drugi,
     * a komenda przestawiała trzeci. Ta sama zasada, dla której sesja zdalna jest
     * jedna (krok 48), a odtwarzacz playlisty — jeden (krok 45).
     */
    private function containers(): ContainerList
    {
        return $this->containers ??= new ContainerList($this->api(), new DockerJsonReader());
    }

    private function images(): ImageList
    {
        return $this->images ??= new ImageList($this->api(), new DockerJsonReader());
    }

    private function logs(): LogStream
    {
        return $this->logs ??= new LogStream(
            $this->api(),
            new LogFrameReader(),
            DockerSettings::logLinesFrom($this->settings->current()),
        );
    }

    private function builds(): BuildFlow
    {
        return $this->builds ??= new BuildFlow(
            $this->work(),
            $this->images(),
            $this->translator,
            $this->state,
        );
    }

    private function work(): BuildWork
    {
        return $this->work ??= new BuildWork($this->api(), new BuildContextPacker(), new BuildProgressReader());
    }

    /**
     * Wypychanie — **jedno na moduł**, jak budowa.
     *
     * Czytnik postępu jest ten sam, co przy budowie, i nie jest to oszczędność:
     * demon nadaje jedno i drugie tym samym strumieniem obiektów JSON.
     */
    private function pushWork(): PushWork
    {
        return $this->pushWork ??= new PushWork($this->api(), new BuildProgressReader());
    }

    private function pullWork(): PullWork
    {
        return $this->pullWork ??= new PullWork($this->api(), new BuildProgressReader());
    }

    private function pulls(): PullFlow
    {
        return $this->pulls ??= new PullFlow(
            $this->pullWork(),
            $this->translator,
            $this->registries(),
            $this->reader(),
        );
    }

    private function pushes(): PushFlow
    {
        return $this->pushes ??= new PushFlow($this->pushWork(), $this->translator, $this->registries(), $this->reader());
    }

    private function composeFlow(): ComposeFlow
    {
        return $this->composeFlow ??= new ComposeFlow($this->compose(), $this->reader(), $this->translator);
    }

    private function api(): DockerApiPort
    {
        return $this->api ?? DockerApiService::getInstance();
    }

    private function compose(): ComposePort
    {
        return $this->compose ?? ComposeCliService::getInstance();
    }

    /** @return list<CommandInterface> */
    private function assemble(): array
    {
        $screen = $this->screen();

        return [
            new PsCommand($screen),
            new ImagesCommand($screen),
            new BuildCommand($this->builds(), $screen),
            new ComposeUpCommand($this->composeFlow(), $screen, $this->environments(), $this->translator),
            new ComposeDownCommand($this->composeFlow(), $screen),
            new PushCommand($this->pushes(), $this->reader(), $this->translator),
            new PullCommand($this->pulls(), $this->translator),
        ];
    }
}
