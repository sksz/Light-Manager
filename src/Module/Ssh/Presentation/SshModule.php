<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Presentation;

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
use LightManager\Module\Ssh\Application\Port\HostBookPort;
use LightManager\Module\Ssh\Application\Port\RemoteDirectoryPort;
use LightManager\Module\Ssh\Application\Port\RemoteTransferPort;
use LightManager\Module\Ssh\Application\Port\SshSessionPort;
use LightManager\Module\Ssh\Application\RemoteBrowser;
use LightManager\Module\Ssh\Application\SshEvent;
use LightManager\Module\Ssh\Application\SshSession;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Infrastructure\OpenSshSessionService;
use LightManager\Module\Ssh\Infrastructure\RemoteTransferService;
use LightManager\Module\Ssh\Infrastructure\SftpDirectoryService;
use LightManager\Module\Ssh\Infrastructure\SshStateService;
use LightManager\Module\Ssh\Presentation\Command\ConnectCommand;
use LightManager\Module\Ssh\Presentation\Command\DisconnectCommand;
use LightManager\Module\Ssh\Presentation\Command\DownloadCommand;
use LightManager\Module\Ssh\Presentation\Command\HostsCommand;
use LightManager\Module\Ssh\Presentation\Command\UploadCommand;
use LightManager\Module\Ssh\Presentation\Query\EntriesQuery;
use LightManager\Module\Ssh\Presentation\Query\HostsQuery;
use LightManager\Module\Ssh\Presentation\Query\SessionQuery;
use LightManager\Module\Ssh\Presentation\Query\TransferQuery;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Cli\Query\CoreReader;
use LightManager\Presentation\Ui\Module\ProvidesHelpTab;
use LightManager\Presentation\Ui\Module\ProvidesScreen;

/**
 * Sesja zdalna jako moduł (krok 48) — **czwarty sprawdzian kontraktu z kroku 20**.
 *
 * Po module rysującym główną funkcję aplikacji (21), module bez ekranu (36)
 * i module pracującym, gdy go nie widać (45), przychodzi moduł **rozmawiający
 * z czymś poza maszyną**. Rdzeń kosztuje pozycję na liście w `Bootstrapie`
 * i dwie rzeczy rozstrzygnięte jawnie (D87): tryb maskowany `TextInput`
 * i zdolność `RequiresEnvironment`. Ani jednej linii więcej.
 *
 * **Moduł jest pierwszym, który odmawia startu** (`RequiresEnvironment`). Droga
 * modułu dźwięku — port z pustym obiektem — była tu rozważona i odrzucona
 * (D87 nr 11): cisza jest sensowną postacią muzyki, ale spis hostów, z którymi
 * nie da się połączyć, nie jest sensowną postacią sesji zdalnej. Obiecywałby.
 *
 * **Jest zarazem pierwszym sprawdzianem zdarzeń z kroku 46 przez moduł, którego
 * przy ich powstawaniu nie było.** Kosztowały enum i jedną zdolność — czyli
 * dokładnie tyle, ile miały kosztować, a zamknięcie słownika z 11o'' obroniło
 * się bez poprawki.
 *
 * Składa się leniwie, jak wszystkie: napisy wchodzą do katalogu **po** zbudowaniu
 * rejestru modułów, więc komenda ani ekran zbudowane zachłannie mogłyby wypisać
 * użytkownikowi surowy klucz. Książki hostów **nie czyta**, dopóki ktoś o nią nie
 * zapyta — uruchomienie aplikacji nie kosztuje ani jednego odczytu z dysku
 * i ani jednego bajtu w sieci (D87 nr 10: autostartu nie ma).
 */
final class SshModule implements
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
     * Litera skrótu.
     *
     * `s` była wolna: zajęte są `b` (przeglądarka), `d` (opis pliku) i `a`
     * (dźwięk), a `c`, `h`, `i`, `j`, `m` i `z` odpadają z przyczyn
     * terminalowych (`ModuleRegistry::FORBIDDEN_CHARACTERS`).
     *
     * `Ctrl`+`S` jest przy tym w terminalu bezpieczny mimo XOFF, bo
     * `TerminalService::RAW_MODE_SETTINGS` zawiera `-ixon` — sprawdzone przy
     * planowaniu kroku i potwierdzone na jego starcie.
     */
    private const SHORTCUT = 's';

    /** @var list<CommandInterface>|null */
    private ?array $commands = null;

    private ?SshSession $session = null;

    private ?SshScreen $screen = null;

    private ?RemoteBrowser $browser = null;

    private ?ConnectFlow $flow = null;

    private ?RemoteTransfer $transfers = null;

    private ?LocalPlace $place = null;

    private ?SshQueries $reader = null;

    /**
     * @param ?SshSessionPort $sessions wstrzyknięcie istnieje dla testów, które nie mają
     *                                  prawa otworzyć połączenia — tak samo, jak testy
     *                                  dźwięku nie mają prawa uruchomić silnika.
     *                                  `null` znaczy „weź usługę na kliencie OpenSSH"
     * @param ?HostBookPort        $storage     jw. — test nie ma prawa dotknąć pliku w katalogu domowym
     * @param ?RemoteDirectoryPort  $directories jw. — odczyt katalogu uruchamia proces potomny,
     *                                           więc test dostaje atrapę (krok 49)
     * @param ?RemoteTransferPort   $files       jw. — przesył uruchamia proces potomny **i pisze
     *                                           po dysku** (krok 50), więc test dostaje atrapę
     */
    public function __construct(
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $settings,
        private readonly ?SshSessionPort $sessions = null,
        private readonly ?HostBookPort $storage = null,
        private readonly ?RemoteDirectoryPort $directories = null,
        private readonly ?RemoteTransferPort $files = null,
    ) {
    }

    public function id(): string
    {
        return SshSettings::ID;
    }

    public function nameKey(): string
    {
        return 'module.' . SshSettings::ID . '.name';
    }

    public function descriptionKey(): string
    {
        return 'module.' . SshSettings::ID . '.description';
    }

    /**
     * Czego brakuje, żeby moduł miał czym działać.
     *
     * Pytanie pada **raz, w ścieżce startu aplikacji**, więc kosztuje przejście
     * po `PATH` i `is_executable()` — nigdy uruchomienia programu, którego się
     * szuka. Moduł z podstawionym portem (test) nie pyta o nic: atrapa nie
     * potrzebuje klienta, a start testu nie ma prawa zależeć od tego, co jest
     * zainstalowane na maszynie, która go uruchamia.
     */
    public function unavailableReason(): ?string
    {
        if ($this->sessions !== null) {
            return null;
        }

        return OpenSshSessionService::hasClient()
            ? null
            : 'module.' . SshSettings::ID . '.unavailable.client';
    }

    /** `Ctrl`+`S` otwiera spis hostów. */
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
        return new ModuleSettingsTab($this->nameKey(), SshSettings::declarations());
    }

    public function events(): array
    {
        return SshEvent::declarations();
    }

    public function commands(): array
    {
        return $this->commands ??= $this->assemble();
    }

    /**
     * Cztery źródła danych tego modułu — **cała jego cena w kroku 54**.
     *
     * Zdolność deklaruje się osobno, jak `ProvidesCommands` i `DeclaresEvents`,
     * bo nie wymienia ani jednego typu z `Presentation` (kryterium podziału
     * z D38 P2). Miara druga kroku 54 brzmi: „jeśli dopisanie kwerend do gotowego
     * modułu kosztuje więcej niż jedną zdolność, mechanizm z kroku 53 wyszedł
     * źle" — tutaj kosztowało tyle i ani grama więcej.
     */
    public function queries(): array
    {
        return [
            new HostsQuery($this->session()),
            new SessionQuery($this->session()),
            new EntriesQuery($this->browser(), $this->translator),
            new TransferQuery($this->transfers(), $this->translator),
        ];
    }

    public function screen(): SshScreen
    {
        return $this->screen ??= new SshScreen(
            $this->session(),
            $this->browser(),
            new HostsScreen($this->session(), $this->flow(), $this->translator, $this->reader()),
            new RemoteScreen(
                $this->browser(),
                $this->translator,
                new ChangeModuleSettingUseCase($this->settings, $this->translator),
                $this->state,
                $this->transfers(),
                $this->reader(),
                new CoreReader($this->state->queries()),
            ),
            $this->state,
            $this->local(),
            $this->reader(),
        );
    }

    /**
     * Przesył — **jeden na moduł**, jak sesja i chodzenie po katalogu (krok 50).
     *
     * Dzielą go ekran (`F5`, `F6`) i dwie komendy, i to jest cały powód, dla
     * którego jest osobną klasą (11n). Dwie implementacje pamiętałyby o kolizji
     * nazw dopóty, dopóki ktoś nie poprawiłby jednej z nich.
     */
    private function transfers(): RemoteTransfer
    {
        return $this->transfers ??= new RemoteTransfer(
            $this->browser(),
            $this->files ?? RemoteTransferService::getInstance(),
            $this->local(),
            $this->translator,
            $this->state->events(),
            $this->reader(),
        );
    }

    /**
     * Zatrzask ostatniego miejsca na tej maszynie — **jeden na moduł**, bo
     * zapisuje go ekran, a czyta praca przesyłu.
     */
    private function local(): LocalPlace
    {
        return $this->place ??= new LocalPlace();
    }

    /**
     * Fasada odczytu — **jedna na moduł** (krok 53, D92 nr 3; ten moduł dostał ją
     * w kroku 54).
     *
     * Jedna, bo dwie znaczyłyby dwa miejsca rozpakowujące ładunek kwerendy, a więc
     * dwie prawdy o tym, co jest w panelu. Powstaje leniwie, jak wszystko w tym
     * module: rejestr kwerend musi być wypełniony **zanim** ktokolwiek zapyta,
     * a wypełnia go `Bootstrap` po zbudowaniu rejestru modułów.
     */
    private function reader(): SshQueries
    {
        return $this->reader ??= new SshQueries($this->state->queries());
    }

    /**
     * Chodzenie po zdalnym katalogu — **jedno na moduł**, jak sesja (krok 49).
     *
     * Wpisy ukryte biorą wartość początkową z ustawień modułu, a `Ctrl`+`H`
     * zmienia ją w trakcie i zapisuje z powrotem — czyli pozycja i klawisz
     * opisują jedną rzecz, a nie dwie.
     */
    public function browser(): RemoteBrowser
    {
        return $this->browser ??= new RemoteBrowser(
            $this->directories ?? SftpDirectoryService::getInstance(),
            $this->storage ?? SshStateService::getInstance(),
            SshSettings::showsHiddenFrom($this->settings->current()),
        );
    }

    /**
     * Takt modułu — **jedno posunięcie pracy i nic więcej**.
     *
     * Warunek z D82 („takt wchodzi wtedy, gdy bez niego funkcja nie istnieje")
     * jest tu spełniony wprost, a nie naciągnięty: sesja żyje w procesie
     * potomnym, więc ktoś musi co klatkę zajrzeć, czy łączenie się skończyło.
     * Bez tego „łączę…" nigdy nie zmieniłoby się w „połączono".
     *
     * Czasu ten moduł nie używa i o niego nie prosi — `poll()` odpowiada „już"
     * albo „jeszcze nie", a nie „od kiedy”. Zegar bez odbiorcy byłby polem, które
     * ktoś kiedyś weźmie za źródło prawdy o klatce.
     */
    public function tick(float $now): void
    {
        // Takt idzie przez **ekran**, a nie wprost do sesji, bo od kroku 49 są
        // dwie prace i jedna decyzja: sesja, odczyt katalogu i rozstrzygnięcie,
        // którą postać widać. Rozdzielone znaczyłyby dwa miejsca, które muszą
        // się zgadzać co klatkę.
        $this->screen()->tick();
    }

    /**
     * Część własna zakładki pomocy — to, czego z deklaracji wyczytać się nie da.
     *
     * Pięć zdań i każde odpowiada na pytanie, które użytkownik zada: czym się to
     * otwiera, skąd biorą się hosty, czym się przedstawia, co się dzieje przy
     * nieznanym odcisku i dlaczego stan bywa nieświeży.
     */
    public function helpKeys(): array
    {
        return [
            'module.' . SshSettings::ID . '.help.start',
            'module.' . SshSettings::ID . '.help.hosts',
            'module.' . SshSettings::ID . '.help.auth',
            'module.' . SshSettings::ID . '.help.fingerprint',
            'module.' . SshSettings::ID . '.help.remote',
            'module.' . SshSettings::ID . '.help.hidden',
            'module.' . SshSettings::ID . '.help.refresh',
            'module.' . SshSettings::ID . '.help.transfer',
            'module.' . SshSettings::ID . '.help.collision',
        ];
    }

    /**
     * Sesja — **jedna na moduł**.
     *
     * Jedna, bo inaczej takt pilnowałby jednego stanu, ekran pokazywał drugi,
     * a komenda przestawiała trzeci. Ta sama zasada, dla której odtwarzacz
     * playlisty jest jeden (krok 45): dwa obiekty znaczą dwie prawdy.
     */
    public function session(): SshSession
    {
        return $this->session ??= new SshSession(
            $this->sessions ?? OpenSshSessionService::getInstance(),
            $this->storage ?? SshStateService::getInstance(),
            $this->settings,
            $this->state->events(),
        );
    }

    /**
     * Łańcuch okien łączenia — **jeden na moduł**, jak sesja.
     *
     * Dzielą go ekran i komenda `ssh.connect`, i to jest cały powód, dla którego
     * jest osobną klasą (11n). Dwa łańcuchy znaczyłyby dwie kolejności okien.
     */
    private function flow(): ConnectFlow
    {
        return $this->flow ??= new ConnectFlow($this->session(), $this->reader(), $this->translator);
    }

    /** @return list<CommandInterface> */
    private function assemble(): array
    {
        $session = $this->session();

        return [
            new ConnectCommand($this->reader(), $this->flow(), $this->translator),
            new DisconnectCommand($session, $this->reader(), $this->translator),
            new HostsCommand(),
            new DownloadCommand($this->transfers(), $this->translator),
            new UploadCommand($this->transfers(), $this->translator),
        ];
    }
}
