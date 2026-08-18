<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use Closure;
use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundStage;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Infrastructure\Process\BackgroundProcessService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Docker\Application\Port\TunnelPort;
use LightManager\Module\Docker\Application\TunnelState;

/**
 * Tunel gniazda demona przez `ssh -L` (krok 58) — praca, która przeżywa swój
 * uchwyt.
 *
 * Wzorzec z kroku 48 wzięty w całości: `ssh -M -N -f` demonizuje się sam, więc
 * uchwyt pracy tłowej gaśnie, a na dysku zostają **dwa pliki** — gniazdo
 * przywiezione (`-L`) i gniazdo mistrza (`ControlPath`), którym tunel się
 * potem zamyka. `-O exit` rozmawia z gniazdem na dysku, nie z siecią, więc
 * zamknięcie nie łamie reguły „żadne wywołanie sieciowe nie pada w klatce".
 *
 * **Gniazdo leży w `XDG_RUNTIME_DIR`** (`/run/user/<uid>`), a nie w `/tmp`:
 * katalog jest prywatny dla użytkownika, a gniazdo daje pełną władzę nad
 * demonem po drugiej stronie. W braku zmiennej (sesja bez systemd, kontener)
 * zapas stanowi `~/.light-manager` — ten sam katalog `0700`, w którym leży
 * gniazdo mistrza modułu Ssh (rozstrzygnięcie ze startu kroku, D102 nr 1).
 * **Nazwa gniazda zawiera nazwę wpisu**, bo dwa środowiska mają prawo stać
 * jednocześnie.
 *
 * **`ExitOnForwardFailure=yes` jest tu warunkiem prawdomówności**: bez niego
 * klient demonizuje się także wtedy, gdy przekierowanie nie wstało — czyli
 * „tunel stoi" znaczyłoby „uwierzytelniłem się", a pierwsze pytanie do demona
 * wisiałoby na gnieździe, którego nie ma.
 *
 * Uwierzytelnienie idzie drogami nieinteraktywnymi (`BatchMode=yes` — agent,
 * klucz z konfiguracji klienta): port pracy tłowej nie podaje potomkowi
 * wejścia, a bez `BatchMode` klient pytałby o hasło na terminalu, którego nie
 * ma, i stał do limitu czasu zamiast powiedzieć „odmowa".
 *
 * Strumienie wolno tu scalić (`2>&1`): wypis mistrza z `-N` jest krótki
 * i diagnostyczny — dokładnie przypadek, który reguła 15f wymienia jako
 * dozwolony — a powód niepowodzenia bierze się z ostatniego wiersza.
 */
final class SocketTunnelService extends AbstractSingleton implements TunnelPort
{
    private const DIRECTORY = '.light-manager';

    private const PREFIX = 'lm-docker-';

    private const SOCKET_SUFFIX = '.sock';

    private const CONTROL_SUFFIX = '.ctl';

    private const DIRECTORY_MODE = 0o700;

    private const CONNECT_TIMEOUT = 10;

    /**
     * Zmienna, spod której `bin/ssh-askpass` bierze hasło — ta sama, której
     * używa moduł Ssh; pomocnik jest narzędziem repozytorium, nie kodem
     * tamtego modułu, więc reguła 15 zostaje nietknięta.
     */
    private const PASSWORD_VARIABLE = 'LM_SSH_PASSWORD';

    /**
     * Zapas ponad limit połączenia — żeby o przekroczeniu mówił klient swoim
     * komunikatem, a nie strażnik portu swoim (wzorem kroku 48).
     */
    private const TIMEOUT_MARGIN = 5;

    private TunnelState $state;

    private ?BackgroundHandle $handle = null;

    /** Wpis, dla którego tunel stoi albo wstaje — potrzebny do `-O exit`. */
    private ?string $name = null;

    private ?string $target = null;

    private int $port = 22;

    private bool $cleanupRegistered = false;

    private ?BackgroundProcessPort $processes = null;

    /** @var ?Closure(string): void */
    private ?Closure $execSeam = null;

    protected function __construct()
    {
        $this->state = TunnelState::none();
    }

    /**
     * Podstawienie portu pracy tłowej i wywołania `-O exit` — **wyłącznie dla
     * testów** (wzorem compose).
     *
     * Szew na `exec()` istnieje, bo kryterium fazy brzmi „żaden test nie
     * uruchamia ani `ssh`, ani `docker`" — a zamknięcie tunelu idzie poza
     * portem pracy tłowej (lekcja z kroku 48: port prowadzony przez kogoś
     * innego mógłby zamknięcie wyprzeć).
     *
     * @param ?Closure(string): void $exec
     */
    public function useSeam(BackgroundProcessPort $processes, ?Closure $exec = null): void
    {
        $this->processes = $processes;
        $this->execSeam = $exec;
    }

    public function state(): TunnelState
    {
        return $this->state;
    }

    public function open(string $name, string $target, int $port, string $remoteSocket, ?string $password = null): void
    {
        $this->close();
        $this->registerCleanup();

        $this->name = $name;
        $this->target = $target;
        $this->port = $port;

        $socket = self::socketFor($name);

        // Gniazdo po nieżyjącym tunelu jest gorsze od jego braku: `connect()`
        // w nie trafia i wisi. Świeży tunel zaczyna od czystego miejsca;
        // `file_exists()`, nie `is_file()` — gniazdo nie jest zwykłym plikiem
        // (lekcja z kroku 49).
        if (file_exists($socket)) {
            @unlink($socket);
        }

        $this->state = TunnelState::starting();

        // Hasło wchodzi do środowiska **tuż przed** uruchomieniem i wychodzi
        // z niego zaraz po — potomek dostaje kopię przy rozwidleniu (wzorzec
        // `OpenSshSessionService::startMaster()` z kroku 48). W wierszu
        // polecenia hasła nie ma: wiersz widzi każdy proces w systemie.
        if ($password !== null) {
            putenv(self::PASSWORD_VARIABLE . '=' . $password);
        }

        $this->handle = $this->processes()->start(
            $this->masterCommand($name, $target, $port, $remoteSocket, usesPassword: $password !== null),
            self::CONNECT_TIMEOUT + self::TIMEOUT_MARGIN,
        );

        if ($password !== null) {
            putenv(self::PASSWORD_VARIABLE);
        }
    }

    public function advance(): void
    {
        if ($this->handle === null) {
            return;
        }

        $result = $this->processes()->poll($this->handle);

        if ($result->stage === BackgroundStage::Running) {
            return;
        }

        $this->handle = null;

        if ($result->stage === BackgroundStage::Idle) {
            $this->state = TunnelState::failed('module.docker.tunnel.interrupted');

            return;
        }

        if ($result->stage === BackgroundStage::Done && ($result->exitCode ?? 1) === 0) {
            $this->state = TunnelState::up(self::socketFor($this->name ?? ''));

            return;
        }

        $this->state = TunnelState::failed('module.docker.tunnel.rejected', [
            'reason' => self::reasonFrom($result->output, $result->problemKey),
        ]);
    }

    /**
     * Zamyka tunel — drogą, której nikt nie może ubić (lekcja z kroku 48:
     * zamknięcie przez port pracy tłowej przerywało najbliższe cudze
     * zamówienie). `-O exit` idzie przez gniazdo mistrza; przekierowanie i `&`
     * sprawiają, że pętla nie czeka ani milisekundy.
     *
     * Gniazdo przywiezione kasujemy sami — `ssh` zostawia je po sobie (plan
     * kroku mówi to wprost); gniazdo mistrza zdejmuje mistrz.
     */
    public function close(): void
    {
        $this->handle = null;
        $name = $this->name;

        if ($name === null) {
            $this->state = TunnelState::none();

            return;
        }

        if ($this->target !== null) {
            $this->execute($this->controlCommand($name, $this->target, $this->port, 'exit') . ' >/dev/null 2>&1 &');
        }

        $socket = self::socketFor($name);

        if (file_exists($socket)) {
            @unlink($socket);
        }

        $this->name = null;
        $this->target = null;
        $this->state = TunnelState::none();
    }

    /**
     * Sprzątanie przy wyjściu — **synchroniczne, bo pętla już nie rysuje**
     * (wzorem `OpenSshSessionService::shutdown()`). Po wyjściu z aplikacji nie
     * ma prawa zostać ani jeden proces `ssh` i ani jedno gniazdo — to jest
     * kryterium ukończenia kroku, nie ostrożność.
     */
    public function shutdown(): void
    {
        $this->handle = null;
        $name = $this->name;

        if ($name === null) {
            return;
        }

        if ($this->target !== null) {
            $this->execute($this->controlCommand($name, $this->target, $this->port, 'exit') . ' >/dev/null 2>&1');
        }

        foreach ([self::socketFor($name), self::controlSocketFor($name)] as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        $this->name = null;
        $this->target = null;
        $this->state = TunnelState::none();
    }

    /** Ścieżka gniazda przywiezionego — liczona w jednym miejscu (wzorem `ControlSocket`). */
    public static function socketFor(string $name): string
    {
        return self::directory() . DIRECTORY_SEPARATOR . self::PREFIX . $name . self::SOCKET_SUFFIX;
    }

    private static function controlSocketFor(string $name): string
    {
        return self::directory() . DIRECTORY_SEPARATOR . self::PREFIX . $name . self::CONTROL_SUFFIX;
    }

    /**
     * `XDG_RUNTIME_DIR`, a w jego braku `~/.light-manager` (D102 nr 1).
     *
     * Obie drogi dają katalog prywatny dla użytkownika — a prywatność nie jest
     * tu ozdobą, bo gniazdo daje pełną władzę nad demonem po drugiej stronie.
     */
    private static function directory(): string
    {
        $runtime = getenv('XDG_RUNTIME_DIR');

        if (is_string($runtime) && $runtime !== '' && is_dir($runtime)) {
            return rtrim($runtime, DIRECTORY_SEPARATOR);
        }

        $home = getenv('HOME');

        if (!is_string($home) || $home === '') {
            $working = getcwd();
            $home = $working === false ? '.' : $working;
        }

        $directory = rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DIRECTORY;

        if (!is_dir($directory)) {
            @mkdir($directory, self::DIRECTORY_MODE, true);
        }

        return $directory;
    }

    private function masterCommand(
        string $name,
        string $target,
        int $port,
        string $remoteSocket,
        bool $usesPassword,
    ): string {
        $options = [
            'ControlMaster=yes',
            'ControlPath=' . self::controlSocketFor($name),
            'ControlPersist=yes',
            'ConnectTimeout=' . self::CONNECT_TIMEOUT,
            'ExitOnForwardFailure=yes',
            // Sposób uwierzytelnienia — te same dwa zestawy, co w module Ssh
            // (krok 48) i z tych samych powodów: `BatchMode=yes` przy kluczu,
            // żeby odmowa była odmową, a nie czekaniem na terminal, którego nie
            // ma; przy haśle jedna próba i wyłączony klucz publiczny, żeby
            // klient nie przedstawiał się niczym, czego użytkownik nie podał.
            ...($usesPassword
                ? [
                    'BatchMode=no',
                    'PreferredAuthentications=password,keyboard-interactive',
                    'PubkeyAuthentication=no',
                    'NumberOfPasswordPrompts=1',
                ]
                : ['BatchMode=yes']),
        ];

        $command = $this->askpassPrefix($usesPassword) . 'ssh -M -N -f -p ' . $port;

        foreach ($options as $option) {
            $command .= ' -o ' . escapeshellarg($option);
        }

        $command .= ' -L ' . escapeshellarg(self::socketFor($name) . ':' . $remoteSocket);

        return $command . ' ' . escapeshellarg($target) . ' 2>&1';
    }

    /**
     * Jak hasło dociera do klienta — **przez `SSH_ASKPASS`, nie przez wejście**
     * (wzorzec kroku 48): `ssh` czyta hasło z terminala sterującego, a port
     * pracy tłowej nie podaje potomkowi wejścia. `SSH_ASKPASS_REQUIRE=force`
     * każe użyć pomocnika niezależnie od terminala i `DISPLAY`.
     */
    private function askpassPrefix(bool $usesPassword): string
    {
        if (!$usesPassword) {
            return '';
        }

        return 'SSH_ASKPASS=' . escapeshellarg(dirname(__DIR__, 4) . '/bin/ssh-askpass')
            . ' SSH_ASKPASS_REQUIRE=force ';
    }

    private function controlCommand(string $name, string $target, int $port, string $action): string
    {
        return sprintf(
            'ssh -O %s -o %s -p %d %s',
            escapeshellarg($action),
            escapeshellarg('ControlPath=' . self::controlSocketFor($name)),
            $port,
            escapeshellarg($target),
        );
    }

    /** Powód z ostatniego wiersza wypisu klienta — cytat, nie tłumaczenie. */
    private static function reasonFrom(string $output, ?string $problemKey): string
    {
        $lines = array_values(array_filter(
            array_map(trim(...), explode("\n", $output)),
            static fn (string $line): bool => $line !== '',
        ));

        $last = $lines === [] ? '' : $lines[count($lines) - 1];

        return $last !== '' ? $last : ($problemKey ?? '');
    }

    /**
     * Druga droga sprzątania (D47) — rejestrowana leniwie, przy pierwszym
     * tunelu: uruchomienie, w którym nikt nie podniósł tunelu, nie ma po czym
     * sprzątać.
     */
    private function registerCleanup(): void
    {
        if ($this->cleanupRegistered) {
            return;
        }

        $this->cleanupRegistered = true;
        register_shutdown_function($this->shutdown(...));
    }

    private function processes(): BackgroundProcessPort
    {
        return $this->processes ?? BackgroundProcessService::getInstance();
    }

    private function execute(string $command): void
    {
        if ($this->execSeam !== null) {
            ($this->execSeam)($command);

            return;
        }

        @exec($command);
    }
}
