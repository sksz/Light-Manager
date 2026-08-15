<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Infrastructure;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundStage;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Infrastructure\Process\BackgroundProcessService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Ssh\Application\Port\SshSessionPort;
use LightManager\Module\Ssh\Application\SessionStage;
use LightManager\Module\Ssh\Application\SessionState;
use LightManager\Module\Ssh\Domain\ValueObject\AuthMethod;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;

/**
 * Sesja zdalna prowadzona **klientem OpenSSH w procesie potomnym** (krok 48,
 * D87 nr 1, 2, 3 i 9).
 *
 * Plan zakładał tu `ext-ssh2` i sesję w procesie aplikacji. Rozstrzygnięcie
 * startowe odwróciło to w całości, bo rozszerzenie nie ma **ani jednego**
 * wywołania nieblokującego, a `ssh2_connect()` nie przyjmuje limitu czasu —
 * host nieosiągalny zamroziłby całą aplikację na `default_socket_timeout`,
 * czyli na minutę.
 *
 * **Jak to trwa, skoro każde wywołanie to osobny potomek.** Przez mistrza
 * połączenia: `ssh -M -N -f` zestawia sesję raz i **demonizuje się sam**, więc
 * aplikacja nie trzyma jego potoków ani przez chwilę. Gniazdo zostaje na dysku,
 * a każda późniejsza operacja — także cały krok 49 i 50 — wchodzi przez nie
 * **bez uścisku dłoni**, czyli w milisekundach zamiast w setkach.
 *
 * **Nic tu nie blokuje pętli.** Potomków uruchamia rdzeniowy
 * `BackgroundProcessPort`, a jedyne, co dzieje się w takcie, to `poll()`, który
 * z definicji nie czeka. Reguła nadrzędna Fazy XVII — „żadne wywołanie sieciowe
 * nie pada w rysowaniu klatki" — jest tu spełniona mocniej, niż brzmi: żadne nie
 * pada w procesie aplikacji w ogóle.
 *
 * **Cena, którą trzeba znać:** port rdzenia prowadzi **jedną pracę naraz**, więc
 * zestawianie sesji przerywa liczenie `du` w module opisu pliku i odwrotnie
 * (D87 nr 9). Przy mistrzu boli to najmniej, bo potomki są krótkie — sam mistrz
 * odchodzi w tło i pracy już nie zajmuje.
 *
 * Strumień błędów sklejamy ze standardowym (`2>&1`) i **wolno nam**, bo mistrz
 * z `-N` na standardowym wyjściu nie pisze nic; bez tego cała diagnostyka
 * klienta przepadłaby, a `BackgroundState` jej nie niesie (patrz
 * `SshFailureReader`).
 */
final class OpenSshSessionService extends AbstractSingleton implements SshSessionPort
{
    /**
     * Ile sekund ponad limit połączenia dać potomkowi, zanim port go ubije.
     *
     * Zapas jest po to, żeby o przekroczeniu czasu mówił **klient** swoim
     * komunikatem, a nie strażnik portu swoim: pierwszy wie, czy nie odpowiedział
     * host, czy nie udało się uwierzytelnienie, drugi wie tylko, że minęło.
     */
    private const TIMEOUT_MARGIN = 5;

    /** Ile czekać na `ssh -O check`/`-O exit` — to jest rozmowa z gniazdem, nie z siecią. */
    private const CONTROL_TIMEOUT = 5;

    /** Zmienna, spod której `bin/ssh-askpass` bierze hasło. */
    public const PASSWORD_VARIABLE = 'LM_SSH_PASSWORD';

    private SessionState $state;

    private ?BackgroundHandle $handle = null;

    /** Profil, o który toczy się bieżąca praca. */
    private ?HostProfile $pending = null;

    /**
     * Hasło żyje **wyłącznie między pytaniem a uruchomieniem potomka**.
     *
     * Trzyma się go tu dlatego, że przy nieznanym hoście między jednym a drugim
     * stoi pytanie o odcisk — a użytkownik, który wpisał hasło i zatwierdził
     * odcisk, nie powinien wpisywać go drugi raz. Znika przy pierwszej okazji:
     * po uruchomieniu mistrza, przy rozłączeniu i przy każdym niepowodzeniu.
     */
    private ?string $password = null;

    private int $timeout = 10;

    private bool $remembers = true;

    private bool $cleanupRegistered = false;

    /**
     * Profil, dla którego **istnieje gniazdo mistrza** — niezależnie od tego, co
     * pokazuje stan.
     *
     * Pole wzięło się z próby z żywym serwerem (krok 48, dziennik): zamykanie
     * mistrza warunkowane etapem `Connected` zostawiało go przy życiu za każdym
     * razem, gdy stan zdążył pójść dalej — a mistrz jest procesem
     * zdemonizowanym, więc nie ginie sam. Gniazdo na dysku jest faktem; etap jest
     * tylko tym, co akurat widać.
     */
    private ?HostProfile $master = null;

    protected function __construct()
    {
        $this->state = SessionState::idle();
    }

    public function useOptions(int $timeoutSeconds, bool $mayRememberHostKeys): void
    {
        $this->timeout = max(1, $timeoutSeconds);
        $this->remembers = $mayRememberHostKeys;
    }

    public function state(): SessionState
    {
        return $this->state;
    }

    public function connect(HostProfile $profile, ?string $password = null): void
    {
        // Jedna sesja naraz (D87 nr 7): to, co trwało, kończy się tutaj — także
        // wtedy, gdy trwało do tego samego hosta.
        $this->disconnect();

        $this->pending = $profile;
        $this->password = $password;

        if ((new KnownHostsReader())->knows($profile->host, $profile->port)) {
            $this->startMaster($profile, acceptNew: false);

            return;
        }

        if (!$this->remembers) {
            // Wyłączona pozycja „zapamiętuj odciski" nie osłabia sprawdzania —
            // odbiera drogę. Połączenie bez weryfikacji nie jest tu wariantem.
            $this->fail($profile, 'module.ssh.problem.unknown-host');

            return;
        }

        $this->state = SessionState::probing($profile);
        $this->handle = $this->processes()->start($this->probeCommand($profile), $this->timeout + self::TIMEOUT_MARGIN);
    }

    public function approve(): void
    {
        if ($this->state->stage !== SessionStage::AwaitingApproval || $this->pending === null) {
            return;
        }

        $this->startMaster($this->pending, acceptNew: true);
    }

    public function advance(): void
    {
        if (!$this->state->isWorking() || $this->handle === null) {
            return;
        }

        $result = $this->processes()->poll($this->handle);

        if ($result->stage === BackgroundStage::Running) {
            return;
        }

        $host = $this->state->host;

        if ($host === null || $result->stage === BackgroundStage::Idle) {
            // Praca wyparta przez inny moduł — port oddaje wtedy `Idle`, a my nie
            // mamy prawa wziąć cudzego wyniku za swój (krok 26, `BackgroundHandle`).
            $this->handle = null;
            $this->state = $host === null ? SessionState::idle() : SessionState::failed($host, 'module.ssh.problem.interrupted');
            $this->password = null;

            return;
        }

        if ($result->stage === BackgroundStage::Failed) {
            $this->fail($host, $result->problemKey ?? 'module.ssh.problem.failed');

            return;
        }

        $this->finish($host, $result->output, $result->exitCode ?? 0);
    }

    public function disconnect(): void
    {
        if ($this->handle !== null) {
            $this->processes()->stop($this->handle);
            $this->handle = null;
        }

        $this->closeMaster();

        $this->pending = null;
        $this->password = null;
        $this->state = SessionState::idle();
    }

    /**
     * Zamyka mistrza, jeśli jakiś stoi — **drogą, której nikt nie może ubić**.
     *
     * Pierwsza wersja szła tu portem pracy tłowej i było to zwyczajnie błędne:
     * port prowadzi **jedną pracę naraz**, więc najbliższe `connect()` przerywało
     * zamknięcie, zanim zdążyło zadziałać. W próbie z żywym serwerem zostawiło to
     * dwóch zdemonizowanych mistrzów i gniazdo po nich.
     *
     * Stąd `exec()` z odłączeniem: `ssh -O exit` rozmawia z **gniazdem na dysku**,
     * nie z siecią, więc nie łamie reguły „żadne wywołanie sieciowe nie pada
     * w klatce"; przekierowanie i `&` sprawiają, że pętla nie czeka ani
     * milisekundy, a potomek nie należy do portu, więc nie ma go czym wyprzeć.
     */
    private function closeMaster(): void
    {
        if ($this->master === null) {
            return;
        }

        $socket = $this->socketFor($this->master);
        @exec($this->controlCommand($this->master, 'exit') . ' >/dev/null 2>&1 &');
        $this->master = null;

        // Gniazda **nie kasujemy tutaj**: mistrz zdejmuje je sam, a usunięcie go
        // spod działającego procesu odebrałoby nam jedyną drogę do niego.
        unset($socket);
    }

    /**
     * Sprawdza, czy gniazdo mistrza jeszcze żyje — **wyłącznie na żądanie**.
     *
     * Powód, dla którego nie robi tego takt, stoi przy `SessionStage::Checking`:
     * potomek co kilka sekund zabijałby cudzą pracę tłową raz na te kilka sekund.
     */
    public function refresh(): void
    {
        $host = $this->state->host;

        if ($host === null || !$this->state->isConnected()) {
            return;
        }

        $this->state = SessionState::checking($host);
        $this->handle = $this->processes()->start($this->controlCommand($host, 'check'), self::CONTROL_TIMEOUT);
    }

    /**
     * Sprzątanie przy wyjściu — **synchroniczne, i to jest tu jedyne miejsce,
     * w którym wolno czekać**.
     *
     * Pętla już nie rysuje, więc nie ma czego zamrozić; `ssh -O exit` rozmawia
     * z gniazdem na dysku, nie z siecią, i kończy się w milisekundach. Droga
     * przez port tłowy byłaby tu gorsza: uruchomiłaby potomka i zaraz potem
     * pozwoliła procesowi się skończyć, czyli ubiłaby go przed skutkiem.
     *
     * Gniazdo kasujemy **po** wywołaniu i tylko wtedy, gdy zostało — mistrz
     * zdejmuje je sam, a plik po nieżyjącym procesie zmyliłby następne
     * uruchomienie.
     */
    public function shutdown(): void
    {
        if ($this->handle !== null) {
            $this->processes()->stop($this->handle);
            $this->handle = null;
        }

        $master = $this->master;

        if ($master !== null) {
            // Przy wyjściu czekamy — **i to jest tu jedyne miejsce, w którym
            // wolno**: pętla już nie rysuje, więc nie ma czego zamrozić, a bez
            // czekania proces skończyłby się przed potomkiem i ubił go razem
            // z sobą. Gniazdo kasujemy **po**, i tylko gdy zostało.
            @exec($this->controlCommand($master, 'exit') . ' >/dev/null 2>&1');

            $socket = $this->socketFor($master);

            // `file_exists()`, a nie `is_file()`: gniazdo uniksowe **nie jest
            // zwykłym plikiem**, więc tamto pytanie odpowiadało „nie ma go"
            // zawsze i sprzątanie nigdy się nie wykonywało. Usterka z kroku 48
            // widoczna dopiero wtedy, gdy ktoś w kroku 49 zapytał o to samo
            // z zewnątrz i zobaczył „BRAK" przy stojącej sesji.
            if (file_exists($socket)) {
                @unlink($socket);
            }

            $this->master = null;
        }

        $this->pending = null;
        $this->password = null;
        $this->state = SessionState::idle();
    }

    /**
     * Czy klient OpenSSH w ogóle jest — odpowiedź dla `RequiresEnvironment`.
     *
     * **Nie uruchamia niczego**: pytanie pada w ścieżce startu aplikacji, więc
     * kosztuje przejście po `PATH` i `is_executable()`, a nie proces potomny.
     */
    public static function hasClient(): bool
    {
        return self::locate('ssh') !== null && self::locate('ssh-keyscan') !== null;
    }

    private static function locate(string $program): ?string
    {
        $path = getenv('PATH');

        if (!is_string($path) || $path === '') {
            return null;
        }

        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            if ($directory === '') {
                continue;
            }

            $candidate = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $program;

            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Rozstrzyga, co znaczy skończona praca — inaczej na każdym etapie. */
    private function finish(HostProfile $host, string $output, int $exitCode): void
    {
        $this->handle = null;

        match ($this->state->stage) {
            SessionStage::Probing => $this->finishProbe($host, $output),
            SessionStage::Connecting => $this->finishConnect($host, $output, $exitCode),
            SessionStage::Checking => $this->finishCheck($host, $exitCode),
            default => null,
        };
    }

    private function finishProbe(HostProfile $host, string $output): void
    {
        $fingerprints = FingerprintParser::parse($output);

        if ($fingerprints === []) {
            // Pusty wynik keyscanu znaczy „host nie odpowiedział" — i to jest
            // zarazem całe sprawdzenie osiągalności, ograniczone w czasie
            // przez `-T`. Osobnego `stream_socket_client()` z planu nie ma po co.
            $this->fail($host, 'module.ssh.problem.unreachable');

            return;
        }

        $this->state = SessionState::awaitingApproval($host, $fingerprints);
    }

    private function finishConnect(HostProfile $host, string $output, int $exitCode): void
    {
        if ($exitCode === 0) {
            $this->password = null;
            $this->master = $host;
            $this->state = SessionState::connected($host);

            return;
        }

        $this->fail($host, SshFailureReader::read($output));
    }

    private function finishCheck(HostProfile $host, int $exitCode): void
    {
        $this->state = $exitCode === 0
            ? SessionState::connected($host)
            : SessionState::failed($host, 'module.ssh.problem.dropped');
    }

    private function fail(HostProfile $host, string $problemKey): void
    {
        $this->handle = null;
        $this->pending = null;
        $this->password = null;
        $this->state = SessionState::failed($host, $problemKey, ['host' => $host->label()]);
    }

    private function startMaster(HostProfile $profile, bool $acceptNew): void
    {
        $this->registerCleanup();
        $this->state = SessionState::connecting($profile);

        // Hasło wchodzi do środowiska **tuż przed** uruchomieniem i wychodzi
        // z niego zaraz po: potomek dostaje kopię przy rozwidleniu, więc
        // zdejmowanie go tutaj nie odbiera mu niczego, a proces aplikacji nie
        // nosi go dłużej, niż musi.
        $password = $this->password;

        if ($password !== null) {
            putenv(self::PASSWORD_VARIABLE . '=' . $password);
        }

        $this->handle = $this->processes()->start(
            $this->masterCommand($profile, $acceptNew),
            $this->timeout + self::TIMEOUT_MARGIN,
        );

        if ($password !== null) {
            putenv(self::PASSWORD_VARIABLE);
        }
    }

    /**
     * `ssh-keyscan … | ssh-keygen -lf -` — odcisk do pokazania w oknie pytania.
     *
     * Potok jest tu dlatego, że `ssh` sam odciska nieznanego hosta **na
     * strumieniu błędów**, a `BackgroundState` go nie niesie (D87 nr 5).
     * Strumień błędów keyscanu wyrzucamy: sypie komentarzami o każdym
     * odpytanym kluczu i zamieniłby odpowiedź w stertę do przeszukania.
     */
    private function probeCommand(HostProfile $profile): string
    {
        return sprintf(
            'ssh-keyscan -T %d -p %d %s 2>/dev/null | ssh-keygen -lf - 2>/dev/null',
            $this->timeout,
            $profile->port,
            escapeshellarg($profile->host),
        );
    }

    private function masterCommand(HostProfile $profile, bool $acceptNew): string
    {
        $options = [
            'ControlMaster=yes',
            'ControlPath=' . $this->socketFor($profile),
            'ControlPersist=yes',
            'ConnectTimeout=' . $this->timeout,
            'StrictHostKeyChecking=' . ($acceptNew ? 'accept-new' : 'yes'),
            // **Ten sam plik, który czyta `KnownHostsReader`** — narzucony wprost,
            // bo `ssh` rozwija `~` z wpisu w `passwd`, a moduł z `HOME`. Na
            // zwykłej maszynie to jedno i to samo, ale w próbie kroku 48 te dwie
            // drogi się rozeszły i zapisany wpis „zniknął" czytającemu.
            'UserKnownHostsFile=' . (new KnownHostsReader())->location(),
            ...$this->authenticationOptions($profile),
        ];

        $command = $this->askpassPrefix($profile) . 'ssh -M -N -f -p ' . $profile->port;

        foreach ($options as $option) {
            $command .= ' -o ' . escapeshellarg($option);
        }

        return $command . ' ' . escapeshellarg($profile->target()) . ' 2>&1';
    }

    /**
     * Jak hasło dociera do klienta — **przez `SSH_ASKPASS`, a nie przez wejście**.
     *
     * Dwa fakty, z których to wynika i których nie da się obejść. Pierwszy:
     * `ssh` czyta hasło **z terminala sterującego**, a nie ze standardowego
     * wejścia, więc podanie go potokiem nie zadziała nawet, gdyby było czym.
     * Drugi: `BackgroundProcessPort` **nie umie podać potomkowi wejścia** i jest
     * to granica postawiona świadomie w kroku 26.
     *
     * `SSH_ASKPASS_REQUIRE=force` (OpenSSH 8.4 wzwyż; na maszynie projektu 9.6)
     * każe klientowi użyć pomocnika **niezależnie od tego, czy widzi terminal
     * i czy jest ustawiony `DISPLAY`** — bez tego zachowanie zależałoby od
     * rzeczy, na które aplikacja nie ma wpływu.
     *
     * **Samo hasło nie stoi w wierszu polecenia** i to jest cała ostrożność tego
     * miejsca: wiersz polecenia widzi każdy proces w systemie (`ps`), a zmienną
     * środowiskową — tylko ten sam użytkownik. Pomocnik nie zawiera sekretu,
     * jest w repozytorium i jedyne, co robi, to wypisanie zmiennej.
     */
    private function askpassPrefix(HostProfile $profile): string
    {
        if ($profile->auth !== AuthMethod::Password) {
            return '';
        }

        return 'SSH_ASKPASS=' . escapeshellarg(self::askpassProgram())
            . ' SSH_ASKPASS_REQUIRE=force ';
    }

    /** Pomocnik leży w repozytorium, obok pozostałych narzędzi projektu. */
    private static function askpassProgram(): string
    {
        return dirname(__DIR__, 4) . '/bin/ssh-askpass';
    }

    /**
     * Opcje sposobu uwierzytelnienia — **jedno miejsce, w którym trzy drogi się
     * różnią**.
     *
     * `BatchMode=yes` przy drogach bezhasłowych nie jest ozdobą: bez niego
     * klient, któremu agent odmówi, zacząłby pytać o hasło — a pyta na terminalu
     * sterującym, którego potomek nie ma, więc stanąłby do limitu czasu zamiast
     * powiedzieć „odmowa".
     *
     * @return list<string>
     */
    private function authenticationOptions(HostProfile $profile): array
    {
        return match ($profile->auth) {
            AuthMethod::Agent => [
                'BatchMode=yes',
                'PreferredAuthentications=publickey',
                'PasswordAuthentication=no',
            ],
            AuthMethod::Key => [
                'BatchMode=yes',
                'PreferredAuthentications=publickey',
                'PasswordAuthentication=no',
                'IdentitiesOnly=yes',
                'IdentityFile=' . ($profile->keyPath ?? ''),
            ],
            AuthMethod::Password => [
                'BatchMode=no',
                'PreferredAuthentications=password,keyboard-interactive',
                'PubkeyAuthentication=no',
                'NumberOfPasswordPrompts=1',
            ],
        };
    }

    private function controlCommand(HostProfile $profile, string $action): string
    {
        return sprintf(
            'ssh -O %s -o %s -p %d %s 2>&1',
            escapeshellarg($action),
            escapeshellarg('ControlPath=' . $this->socketFor($profile)),
            $profile->port,
            escapeshellarg($profile->target()),
        );
    }

    /**
     * Ścieżka gniazda mistrza — **liczona w jednym miejscu dla całego modułu**.
     *
     * Rachunek zszedł w kroku 49 do `ControlSocket`, bo doszedł drugi
     * użytkownik: odczyt zdalnego katalogu wchodzi przez to samo gniazdo.
     * Powtórzony po obu stronach rozjechałby się przy pierwszej poprawce,
     * a rozjazd byłby niewidoczny — wszystko dalej działa, tylko z drugim
     * uściskiem dłoni.
     */
    private function socketFor(HostProfile $profile): string
    {
        return ControlSocket::pathFor($profile);
    }

    /**
     * Druga droga sprzątania (D47) — **rejestrowana leniwie, przy pierwszym
     * mistrzu**.
     *
     * Pierwszą jest `disconnect()` wołany przez moduł. Ta łapie to, czego tamta
     * nie dosięga: błąd krytyczny i wyjście, w którym nikt niczego nie zdążył
     * zawołać. Rejestracja jest leniwa z tego samego powodu, co w silniku audio:
     * uruchomienie aplikacji, w którym nikt się nigdzie nie połączył, nie ma po
     * czym sprzątać.
     *
     * Mistrza zostawionego mimo obu dróg i tak nie ma się co bać w nieskończoność
     * — `ControlPersist` zamknie go, gdy sieć padnie, a gniazdo po nieżyjącym
     * procesie rozpoznaje `ssh -O check` przy następnej próbie.
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
        return BackgroundProcessService::getInstance();
    }
}
