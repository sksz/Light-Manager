<?php

declare(strict_types=1);

namespace LightManager\Module\Kubernetes\Presentation;

use LightManager\Application\Command\CommandInterface;
use LightManager\Application\Dto\Settings;
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
use LightManager\Module\Kubernetes\Application\ApiCatalog;
use LightManager\Module\Kubernetes\Application\ClusterActions;
use LightManager\Module\Kubernetes\Application\Clusters;
use LightManager\Module\Kubernetes\Application\ClusterSession;
use LightManager\Module\Kubernetes\Application\ClusterState;
use LightManager\Module\Kubernetes\Application\ConfigCatalog;
use LightManager\Module\Kubernetes\Application\KubernetesEvent;
use LightManager\Module\Kubernetes\Application\KubernetesSettings;
use LightManager\Module\Kubernetes\Application\LogStream;
use LightManager\Module\Kubernetes\Application\Port\ClusterBookPort;
use LightManager\Module\Kubernetes\Application\Port\KubectlPort;
use LightManager\Module\Kubernetes\Application\ResourceCache;
use LightManager\Module\Kubernetes\Application\ResourceDetail;
use LightManager\Module\Kubernetes\Infrastructure\KubectlService;
use LightManager\Module\Kubernetes\Infrastructure\KubernetesStateService;
use LightManager\Module\Kubernetes\Presentation\Command\ApplyCommand;
use LightManager\Module\Kubernetes\Presentation\Command\ContextCommand;
use LightManager\Module\Kubernetes\Presentation\Command\DeployImageCommand;
use LightManager\Module\Kubernetes\Presentation\Command\GetCommand;
use LightManager\Module\Kubernetes\Presentation\Command\NamespaceCommand;
use LightManager\Module\Kubernetes\Presentation\Query\ClusterQuery;
use LightManager\Module\Kubernetes\Presentation\Query\ClustersQuery;
use LightManager\Module\Kubernetes\Presentation\Query\ContextsQuery;
use LightManager\Module\Kubernetes\Presentation\Query\DeploymentsQuery;
use LightManager\Module\Kubernetes\Presentation\Query\KindsQuery;
use LightManager\Module\Kubernetes\Presentation\Query\NamespacesQuery;
use LightManager\Module\Kubernetes\Presentation\Query\ResourcesQuery;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Cli\Query\CoreReader;
use LightManager\Presentation\Cli\SplitSetting;
use LightManager\Presentation\Ui\Module\ProvidesHelpTab;
use LightManager\Presentation\Ui\Module\ProvidesScreen;

/**
 * Kubernetes jako moduł (krok 52) — **szósty sprawdzian kontraktu z kroku 20**.
 *
 * Po module rysującym główną funkcję aplikacji (21), module bez ekranu (36),
 * module pracującym, gdy go nie widać (45), module rozmawiającym z cudzą maszyną
 * (48) i module prowadzącym kilka rozmów naraz (51) przychodzi moduł, który
 * **nie wie z góry, co pokaże**: rodzaje zasobów przychodzą z klastra, więc
 * drzewo jednego klastra wygląda inaczej niż drugiego, a operator zainstalowany
 * w międzyczasie zmienia je bez jednej linii dopisanej do aplikacji.
 *
 * Rdzeń kosztuje **jedną linię w `Bootstrapie`** — ale nie tylko ją, i tego nie
 * chowamy: krok rozbudował `BackgroundProcessPort` o wypis pracy trwającej
 * (D91 nr 12), bo bez tego `kubectl logs -f` nie miał jak powiedzieć ani słowa.
 * Plan kroku zakładał, że mechanizmu rdzenia nie ruszy żadnego; rozstrzygnięcie
 * użytkownika kazało ruszyć jeden.
 *
 * **Moduł odmawia startu bez `kubectl`** (`RequiresEnvironment`, reguła 11s) —
 * i jest to ta sama odpowiedź, co przy braku klienta `ssh` w kroku 48, a nie ta,
 * co przy leżącym demonie Dockera. Różnica jest mechaniczna: klienta nie da się
 * doinstalować w trakcie działania aplikacji, a klaster **da się podnieść** —
 * i dlatego niedostępny klaster jest ekranem z powodem, a nie powodem
 * nieobecności modułu.
 */
final class KubernetesModule implements
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
     * Litera skrótu — `k` jak `kubectl` (D90 nr 2).
     *
     * Przydzielona **przy kroku 51, dla obu kroków naraz**: propozycja planu
     * brzmiała `u` („kUbernetes”), bo `k` miał wziąć moduł kontenerów. Przegrała
     * jednym argumentem — `k` jest utrwalonym skrótem Kubernetesa poza tą
     * aplikacją (alias `k` dla `kubectl` ma w palcach każdy, kto pracuje
     * z klastrem), więc oddanie go Dockerowi kazałoby temu krokowi wziąć literę
     * słabszą **i** zaskoczyć użytkownika.
     */
    private const SHORTCUT = 'k';

    /** Domyślny podział: drzewo węższe od listy zasobów (krok 55). */
    private const SPLIT_PERCENT = 40;

    /** @var list<CommandInterface>|null */
    private ?array $commands = null;

    private ?ClusterScreen $screen = null;

    private ?ClusterSession $session = null;

    private ?ClusterState $cluster = null;

    private ?Clusters $clusters = null;

    private ?ConfigCatalog $configs = null;

    private ?ClusterBookScreen $bookScreen = null;

    private ?ApiCatalog $catalog = null;

    private ?ResourceCache $cache = null;

    private ?ResourceDetail $detail = null;

    private ?KubernetesQueries $reader = null;

    private ?DeployImageFlow $deploy = null;

    private ?LogStream $logs = null;

    private ?ClusterActions $actions = null;

    /** Czy migracja zapamiętanego miejsca z ustawień do książki już padła (krok 59). */
    private bool $migrated = false;

    /**
     * @param ?KubectlPort $kubectl wstrzyknięcie istnieje **wyłącznie dla testów**,
     *                              które nie mają prawa wywołać `kubectl` (kryterium
     *                              ukończenia kroku). `null` znaczy „weź usługę
     *                              uruchamiającą proces potomny”
     */
    public function __construct(
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $settings,
        private readonly ?KubectlPort $kubectl = null,
        /** Wstrzyknięcie książki — **wyłącznie dla testów**, jak port `kubectl`. */
        private readonly ?ClusterBookPort $bookPort = null,
    ) {
    }

    public function id(): string
    {
        return KubernetesSettings::ID;
    }

    public function nameKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.name';
    }

    public function descriptionKey(): string
    {
        return 'module.' . KubernetesSettings::ID . '.description';
    }

    /**
     * Czego brakuje, żeby moduł miał czym działać.
     *
     * Pytanie pada **raz, w ścieżce startu aplikacji**, więc wolno mu kosztować
     * przejrzenie `PATH` — i ani grama więcej (reguła 11s). Uruchomienie
     * `kubectl version` byłoby tu procesem potomnym w starcie aplikacji, a klaster
     * nieosiągalny kazałby czekać na limit czasu, zanim cokolwiek się narysuje.
     */
    public function unavailableReason(): ?string
    {
        if ($this->kubectl !== null) {
            return null;
        }

        return KubectlService::hasClient()
            ? null
            : 'module.' . KubernetesSettings::ID . '.unavailable.client';
    }

    public function shortcut(): ModuleShortcut
    {
        return new ModuleShortcut(self::SHORTCUT);
    }

    public function translations(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'lang';
    }

    public function settingsTab(): ModuleSettingsTab
    {
        return new ModuleSettingsTab($this->nameKey(), [
            ...KubernetesSettings::declarations(),
            SplitSetting::declaration(ClusterScreen::ID, self::SPLIT_PERCENT),
        ]);
    }

    public function events(): array
    {
        return KubernetesEvent::declarations();
    }

    public function commands(): array
    {
        return $this->commands ??= [
            new GetCommand($this->screen(), $this->catalog(), $this->translator),
            new ContextCommand($this->screen()),
            new NamespaceCommand($this->screen()),
            new ApplyCommand($this->screen(), $this->actions()),
            new DeployImageCommand($this->deployFlow()),
        ];
    }

    public function screen(): ClusterScreen
    {
        return $this->screen ??= new ClusterScreen(
            $this->cluster(),
            $this->clusters(),
            $this->bookScreen(),
            $this->catalog(),
            $this->cache(),
            $this->detail(),
            $this->logs(),
            $this->actions(),
            $this->session(),
            $this->translator,
            $this->state,
            $this->reader(),
            new CoreReader($this->state->queries()),
            SplitSetting::state(
                ClusterScreen::ID,
                self::SPLIT_PERCENT,
                $this->state,
                new ChangeModuleSettingUseCase($this->settings, $this->translator),
            ),
        );
    }

    /**
     * Takt modułu — posunięcie wszystkiego, co trwa poza klatką.
     *
     * Warunek z D82 („takt wchodzi wtedy, gdy bez niego funkcja nie istnieje”)
     * jest spełniony dwiema drogami naraz, tak samo jak w module Dockera.
     * Pierwsza: **strumień nieczytany zatrzymuje nadawcę**, więc logi muszą być
     * pompowane także wtedy, gdy użytkownik zajrzał w ustawienia. Druga: odpowiedź
     * klastra przychodzi wtedy, kiedy przychodzi, a klawisza wtedy nikt nie
     * naciska.
     *
     * Ustawienia czytamy **na początku taktu**, bo limit czasu i granica bufora
     * mają obowiązywać od najbliższego wywołania, a nie od następnego wejścia na
     * ekran.
     */
    public function tick(float $now): void
    {
        $settings = $this->settings->current();
        $this->session()->useTimeout(KubernetesSettings::timeoutFrom($settings));
        $this->logs()->useLimit(KubernetesSettings::logLinesFrom($settings));
        $this->migrateOnce($settings);

        $this->screen()->tick($now, KubernetesSettings::refreshFrom($settings));
    }

    public function helpKeys(): array
    {
        return [
            'module.' . KubernetesSettings::ID . '.help.start',
            'module.' . KubernetesSettings::ID . '.help.tree',
            'module.' . KubernetesSettings::ID . '.help.place',
            'module.' . KubernetesSettings::ID . '.help.detail',
            'module.' . KubernetesSettings::ID . '.help.logs',
            'module.' . KubernetesSettings::ID . '.help.actions',
            'module.' . KubernetesSettings::ID . '.help.secrets',
            'module.' . KubernetesSettings::ID . '.help.versions',
        ];
    }

    /**
     * Przenosi zapamiętane miejsce z pozycji ustawień do książki — **raz, przy
     * pierwszym takcie po wejściu tej wersji modułu** (krok 59, plan punkt 7).
     *
     * Do kroku 59 wybór miejsca mieszkał w dwóch pozycjach ustawień (`context`
     * i `namespace`) i zapisywał się po każdej zmianie. Odtąd mieszka
     * w książce, bo miejsce ma dwie współrzędne i własną tożsamość — a dwie
     * pozycje, których użytkownik nie przestawia strzałkami, były obejściem
     * braku książki, nie ustawieniami.
     *
     * Wartości nie giną, a stare pozycje zostają w `settings.json` nietknięte:
     * nikt ich już nie czyta, a ich skasowanie nie ma odbiorcy.
     */
    private function migrateOnce(Settings $settings): void
    {
        if ($this->migrated) {
            return;
        }

        $this->migrated = true;

        if (!$this->clusters()->isFresh()) {
            return;
        }

        $this->clusters()->migrate(
            KubernetesSettings::contextFrom($settings),
            KubernetesSettings::namespaceFrom($settings),
        );
    }

    private function session(): ClusterSession
    {
        return $this->session ??= new ClusterSession();
    }

    /**
     * Fasada odczytu — **jedna na moduł** (krok 53, D92 nr 3; ten moduł dostał ją
     * w kroku 54).
     */
    private function reader(): KubernetesQueries
    {
        return $this->reader ??= new KubernetesQueries($this->state->queries());
    }

    /**
     * Choreografia `k8s.deploy-image` — **jedna na moduł**, jak wszystko, co ma
     * dwa wejścia (11n).
     *
     * Dostaje **oba rejestry rdzenia**, i to jest cała jej cena: przez rejestr
     * kwerend pyta o cudze dane, przez rejestr komend zamawia cudze czynności.
     * Ani jednego typu z modułu Dockera nie widzi (15g).
     */
    private function deployFlow(): DeployImageFlow
    {
        return $this->deploy ??= new DeployImageFlow(
            $this->state->queries(),
            $this->state->commands(),
            $this->reader(),
            $this->actions(),
            $this->translator,
            $this->settings,
        );
    }

    /**
     * Sześć źródeł danych tego modułu — **najwięcej w kroku i najwięcej
     * w aplikacji**.
     *
     * `k8s.deployments` jest wśród nich tą, na której stoi ostatni etap czynności
     * `k8s.deploy-image`: oddaje wdrożenia wraz z **nazwą kontenera**, bo bez niej
     * `kubectl set image` nie ma czego podmienić.
     */
    public function queries(): array
    {
        return [
            new ContextsQuery($this->cluster()),
            new ClusterQuery($this->cluster()),
            new ClustersQuery($this->clusters()),
            new NamespacesQuery($this->session(), $this->cache()),
            new KindsQuery($this->catalog()),
            new ResourcesQuery($this->catalog(), $this->cache()),
            new DeploymentsQuery($this->catalog(), $this->cache()),
        ];
    }

    private function cluster(): ClusterState
    {
        return $this->cluster ??= new ClusterState($this->kubectl(), $this->session(), $this->clusters());
    }

    /** Koordynator spisu klastrów — jeden na moduł (krok 59). */
    private function clusters(): Clusters
    {
        return $this->clusters ??= new Clusters(
            $this->bookPort ?? KubernetesStateService::getInstance(),
            $this->configs(),
            $this->session(),
        );
    }

    private function configs(): ConfigCatalog
    {
        return $this->configs ??= new ConfigCatalog($this->kubectl(), $this->session());
    }

    private function bookScreen(): ClusterBookScreen
    {
        return $this->bookScreen ??= new ClusterBookScreen(
            $this->clusters(),
            new ClusterFlow($this->clusters(), $this->translator),
            $this->translator,
            $this->reader(),
        );
    }

    private function catalog(): ApiCatalog
    {
        return $this->catalog ??= new ApiCatalog($this->kubectl(), $this->session());
    }

    private function cache(): ResourceCache
    {
        return $this->cache ??= new ResourceCache($this->kubectl(), $this->session());
    }

    private function detail(): ResourceDetail
    {
        return $this->detail ??= new ResourceDetail($this->kubectl(), $this->session());
    }

    private function logs(): LogStream
    {
        return $this->logs ??= new LogStream($this->kubectl());
    }

    private function actions(): ClusterActions
    {
        return $this->actions ??= new ClusterActions($this->kubectl(), $this->session());
    }

    private function kubectl(): KubectlPort
    {
        return $this->kubectl ?? KubectlService::getInstance();
    }
}
