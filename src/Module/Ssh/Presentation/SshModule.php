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
use LightManager\Application\Module\ProvidesSettingsTab;
use LightManager\Application\Module\RequiresEnvironment;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\TranslatorPort;
use LightManager\Module\Ssh\Application\Port\HostBookPort;
use LightManager\Module\Ssh\Application\Port\SshSessionPort;
use LightManager\Module\Ssh\Application\SshEvent;
use LightManager\Module\Ssh\Application\SshSession;
use LightManager\Module\Ssh\Application\SshSettings;
use LightManager\Module\Ssh\Infrastructure\OpenSshSessionService;
use LightManager\Module\Ssh\Infrastructure\SshStateService;
use LightManager\Module\Ssh\Presentation\Command\ConnectCommand;
use LightManager\Module\Ssh\Presentation\Command\DisconnectCommand;
use LightManager\Module\Ssh\Presentation\Command\HostsCommand;
use LightManager\Presentation\Cli\LoopState;
use LightManager\Presentation\Ui\Module\ProvidesHelpTab;
use LightManager\Presentation\Ui\Module\ProvidesScreen;
use LightManager\Presentation\Ui\ScreenInterface;

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

    private ?HostsScreen $screen = null;

    private ?ConnectFlow $flow = null;

    /**
     * @param ?SshSessionPort $sessions wstrzyknięcie istnieje dla testów, które nie mają
     *                                  prawa otworzyć połączenia — tak samo, jak testy
     *                                  dźwięku nie mają prawa uruchomić silnika.
     *                                  `null` znaczy „weź usługę na kliencie OpenSSH"
     * @param ?HostBookPort   $storage  jw. — test nie ma prawa dotknąć pliku w katalogu domowym
     */
    public function __construct(
        private readonly LoopState $state,
        private readonly TranslatorPort $translator,
        private readonly SettingsPort $settings,
        private readonly ?SshSessionPort $sessions = null,
        private readonly ?HostBookPort $storage = null,
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

    public function screen(): ScreenInterface
    {
        return $this->screen ??= new HostsScreen($this->session(), $this->flow(), $this->translator);
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
        $this->session()->tick();
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
            'module.' . SshSettings::ID . '.help.refresh',
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
        return $this->flow ??= new ConnectFlow($this->session(), $this->translator);
    }

    /** @return list<CommandInterface> */
    private function assemble(): array
    {
        $session = $this->session();

        return [
            new ConnectCommand($session, $this->flow(), $this->translator),
            new DisconnectCommand($session, $this->translator),
            new HostsCommand(),
        ];
    }
}
