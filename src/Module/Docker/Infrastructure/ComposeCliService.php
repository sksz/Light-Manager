<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundStage;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Infrastructure\Process\BackgroundProcessService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Docker\Application\ComposeAction;
use LightManager\Module\Docker\Application\ComposeState;
use LightManager\Module\Docker\Application\Port\ComposePort;

/**
 * Wtyczka `docker compose` uruchamiana rdzeniowym portem pracy tłowej (krok 51).
 *
 * **Jedyne miejsce w tym module, które nie rozmawia z gniazdem**, i jedyne,
 * które nie mogło: demon nie wystawia dla compose ani jednego zasobu w API.
 * Praca idzie więc procesem potomnym — tym samym mechanizmem rdzenia, którym
 * moduł opisu pliku liczy zajętość katalogu, a moduł sesji zdalnej przesyła
 * pliki. Podrabiania go w module nie ma i być nie może (reguła 15e).
 *
 * **To jest zarazem pierwszy odbiorca rozbudowy portu z tego samego kroku.**
 * Do kroku 51 port prowadził jedną pracę naraz, więc `compose up` — trwający
 * minutami — zabijałby liczenie zajętości katalogu i przesył pliku. Podniesienie
 * projektu i praca sąsiada stoją odtąd obok siebie, każde pod swoim uchwytem.
 *
 * **Strumieni nie scalamy** (reguła 15f) i przy `ls` jest to warunek
 * poprawności, a nie porządku: wyjściem tego polecenia jest **treść** (JSON ze
 * spisem projektów), a wtyczka pisze na strumieniu błędów ostrzeżenia o wersji
 * pliku i o nieużywanych zmiennych. Sklejone dawałyby JSON, którego nie da się
 * rozczytać — i to nie zawsze, tylko wtedy, gdy akurat coś ostrzeże.
 */
final class ComposeCliService extends AbstractSingleton implements ComposePort
{
    /**
     * Czym się to woła.
     *
     * `docker compose`, a nie `docker-compose`: v2 jest **wtyczką klienta**
     * (sprawdzone: v2.29.7), a osobny plik wykonywalny to v1, wycofana w 2023
     * i nieobecna na maszynie projektu. Wersji nie wykrywamy — obecność wtyczki
     * sprawdza się tanio i raz, przy przyjmowaniu modułu.
     */
    private const BINARY = 'docker';

    private ComposeState $state;

    /** Przedrostek środowiska (krok 58) — `DOCKER_HOST=…` przed poleceniem. */
    private string $prefix = '';

    private ?BackgroundHandle $handle = null;

    private ?ComposeAction $action = null;

    private ?BackgroundProcessPort $processes = null;

    protected function __construct()
    {
        $this->state = ComposeState::idle();
    }

    /**
     * Podstawienie portu pracy tłowej — **wyłącznie dla testów**.
     *
     * Ten sam szew, co w `RemoteTransferService` z kroku 50 i z tego samego
     * powodu: test nie ma prawa uruchomić `docker compose` na maszynie, na
     * której akurat biegnie.
     */
    public function useSeam(BackgroundProcessPort $processes): void
    {
        $this->processes = $processes;
    }

    /** Czy klient Dockera w ogóle jest — pytanie tanie, zadawane raz (reguła 11s). */
    public static function hasClient(): bool
    {
        $path = getenv('PATH');

        if (!is_string($path) || $path === '') {
            return false;
        }

        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            if ($directory !== '' && is_executable(rtrim($directory, '/') . '/' . self::BINARY)) {
                return true;
            }
        }

        return false;
    }

    public function useEnvironment(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function state(): ComposeState
    {
        return $this->state;
    }

    public function begin(ComposeAction $action, ?string $file = null): void
    {
        $this->stop();

        $command = self::commandFor($action, $file);

        if ($command === null) {
            $this->state = ComposeState::failed($action, 'module.docker.compose.noFile');

            return;
        }

        $this->action = $action;
        $this->state = ComposeState::working($action);
        // Przedrostek środowiska idzie przed poleceniem — klient czyta
        // `DOCKER_HOST` i rozmawia z tym samym demonem, co listy modułu.
        // Dotyczy także `ls`: spis projektów z innego demona niż kontenery
        // byłby drugą prawdą o tej samej maszynie.
        $this->handle = $this->processes()->start($this->prefix . $command, $action->timeoutSeconds());
    }

    public function advance(): void
    {
        $action = $this->action;

        if (!$this->state->isWorking() || $this->handle === null || $action === null) {
            return;
        }

        $result = $this->processes()->poll($this->handle);

        if ($result->stage === BackgroundStage::Running) {
            return;
        }

        if ($result->stage === BackgroundStage::Idle) {
            // Port oddaje `Idle` na uchwyt, którego już nie zna — pracę zdjął
            // ktoś, kto ją trzymał, albo jej stan wypadł z zapasu. Cudzego
            // wyniku nie wolno wziąć za swój.
            $this->handle = null;
            $this->state = ComposeState::failed($action, 'module.docker.compose.interrupted');

            return;
        }

        $this->handle = null;

        if ($result->stage === BackgroundStage::Failed) {
            $this->state = ComposeState::failed(
                $action,
                $result->problemKey ?? 'module.docker.compose.failed',
                $result->problemParameters,
            );

            return;
        }

        $this->finish($action, $result->output, $result->errorOutput, $result->exitCode ?? 0);
    }

    public function stop(): void
    {
        if ($this->handle !== null) {
            $this->processes()->stop($this->handle);
            $this->handle = null;
        }

        $this->action = null;
        $this->state = ComposeState::idle();
    }

    /**
     * Rozstrzyga, czym skończyła się praca.
     *
     * **Kod wyjścia rozstrzyga o powodzeniu, a strumień błędów o powodzie** —
     * ta sama kolejność, co przy `sftp` w kroku 49. Wtyczka pisze na strumieniu
     * błędów **także wtedy, gdy się udało** (ostrzeżenia o wersji pliku), więc
     * sam fakt, że coś tam stanęło, nie jest niepowodzeniem.
     */
    private function finish(ComposeAction $action, string $output, string $errorOutput, int $exitCode): void
    {
        if ($exitCode !== 0) {
            $this->state = ComposeState::failed($action, 'module.docker.compose.rejected', [
                'reason' => ComposeListReader::reason($errorOutput),
            ]);

            return;
        }

        $this->state = ComposeState::done(
            $action,
            $action === ComposeAction::ListProjects ? ComposeListReader::projects($output) : [],
            // Ostatni wiersz wypisu — przy `up` i `down` to jedyne zdanie, jakim
            // wtyczka mówi, co właściwie zrobiła.
            ComposeListReader::lastLine($output === '' ? $errorOutput : $output),
        );
    }

    /**
     * Gotowy wiersz polecenia albo `null`, gdy czynności brakuje pliku.
     *
     * Ścieżkę cytujemy **zawsze** — plik compose leży zwykle w katalogu
     * projektu, a te bywają nazwane ze spacją.
     */
    private static function commandFor(ComposeAction $action, ?string $file): ?string
    {
        if ($action === ComposeAction::ListProjects) {
            // `-a` pokazuje także projekty położone. Bez tego lista milczy
            // dokładnie wtedy, gdy użytkownik chce coś podnieść.
            return self::BINARY . ' compose ls -a --format json';
        }

        if ($file === null || trim($file) === '') {
            return null;
        }

        $arguments = $action === ComposeAction::Up ? 'up -d' : 'down';

        return self::BINARY . ' compose -f ' . escapeshellarg($file) . ' ' . $arguments;
    }

    private function processes(): BackgroundProcessPort
    {
        return $this->processes ?? BackgroundProcessService::getInstance();
    }
}
