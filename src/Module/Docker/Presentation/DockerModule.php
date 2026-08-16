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
use LightManager\Module\Docker\Application\ImageList;
use LightManager\Module\Docker\Application\LogStream;
use LightManager\Module\Docker\Application\Port\ComposePort;
use LightManager\Module\Docker\Application\Port\DockerApiPort;
use LightManager\Module\Docker\Application\PushWork;
use LightManager\Module\Docker\Infrastructure\BuildContextPacker;
use LightManager\Module\Docker\Infrastructure\BuildProgressReader;
use LightManager\Module\Docker\Infrastructure\ComposeCliService;
use LightManager\Module\Docker\Infrastructure\DockerApiService;
use LightManager\Module\Docker\Infrastructure\DockerJsonReader;
use LightManager\Module\Docker\Infrastructure\LogFrameReader;
use LightManager\Module\Docker\Presentation\Command\BuildCommand;
use LightManager\Module\Docker\Presentation\Command\ComposeDownCommand;
use LightManager\Module\Docker\Presentation\Command\ComposeUpCommand;
use LightManager\Module\Docker\Presentation\Command\ImagesCommand;
use LightManager\Module\Docker\Presentation\Command\PsCommand;
use LightManager\Module\Docker\Presentation\Command\PushCommand;
use LightManager\Module\Docker\Presentation\Query\BuildQuery;
use LightManager\Module\Docker\Presentation\Query\ComposeQuery;
use LightManager\Module\Docker\Presentation\Query\ContainersQuery;
use LightManager\Module\Docker\Presentation\Query\ImagesQuery;
use LightManager\Module\Docker\Presentation\Query\PushQuery;
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
     * Czego brakuje, żeby moduł miał czym działać.
     *
     * Pytanie pada **raz, w ścieżce startu aplikacji**, więc kosztuje
     * `extension_loaded()` i `file_exists()` — nigdy zapytania do demona
     * (reguła 11s). Moduł z podstawionym portem (test) nie pyta o nic: atrapa nie
     * potrzebuje ani rozszerzenia, ani gniazda, a start testu nie ma prawa
     * zależeć od tego, co jest zainstalowane na maszynie, która go uruchamia.
     */
    public function unavailableReason(): ?string
    {
        if ($this->api !== null) {
            return null;
        }

        if (!extension_loaded('curl')) {
            return 'module.' . DockerSettings::ID . '.unavailable.curl';
        }

        return DockerApiService::isSupported()
            ? null
            : 'module.' . DockerSettings::ID . '.unavailable.socket';
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
            SplitSetting::state(
                DockerSettings::ID,
                self::SPLIT_PERCENT,
                $this->state,
                new ChangeModuleSettingUseCase($this->settings, $this->translator),
            ),
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
            new ImagesQuery($this->images(), $this->translator),
            new ContainersQuery($this->containers()),
            new ComposeQuery($this->compose()),
            new BuildQuery($this->work()),
            new PushQuery($this->pushWork()),
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

    private function pushes(): PushFlow
    {
        return $this->pushes ??= new PushFlow($this->pushWork(), $this->translator, $this->settings);
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
            new ComposeUpCommand($this->composeFlow(), $screen),
            new ComposeDownCommand($this->composeFlow(), $screen),
            new PushCommand($this->pushes(), $this->reader(), $this->translator),
        ];
    }
}
