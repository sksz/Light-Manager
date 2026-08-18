<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Application\TunnelStage;
use LightManager\Module\Docker\Infrastructure\SocketTunnelService;
use LightManager\Tests\Support\ResetsSingletons;
use LightManager\Tests\Support\StubBackgroundProcess;
use PHPUnit\Framework\TestCase;

/**
 * Usługa tunelu `ssh -L` (krok 58) — **bez ani jednego prawdziwego procesu**.
 *
 * Port pracy tłowej podstawia szew, jak w testach compose: sprawdza się kształt
 * wiersza polecenia (bo to on jest umową z klientem OpenSSH) i przejścia stanu,
 * nie sam tunel. Prawdziwy tunel obejrzy dopiero próba na żywym hoście, spisana
 * w dzienniku kroku.
 */
final class SocketTunnelServiceTest extends TestCase
{
    use ResetsSingletons;

    protected function setUp(): void
    {
        $this->resetSingleton(SocketTunnelService::class);
    }

    protected function tearDown(): void
    {
        $this->resetSingleton(SocketTunnelService::class);
    }

    /**
     * Wiersz polecenia mistrza — każda opcja z powodem zapisanym w usłudze.
     *
     * `ExitOnForwardFailure` jest tu najważniejsze: bez niego „tunel stoi"
     * znaczyłoby tylko „uwierzytelniłem się".
     */
    public function testTheMasterCommandCarriesTheForwardAndItsGuards(): void
    {
        $processes = new StubBackgroundProcess();
        $service = SocketTunnelService::getInstance();
        $service->useSeam($processes);

        $service->open('serwer', 'anna@example.com', 2222, '/var/run/docker.sock');

        self::assertCount(1, $processes->startedCommands);
        $command = $processes->startedCommands[0];

        self::assertStringStartsWith('ssh -M -N -f -p 2222', $command);
        self::assertStringContainsString('ExitOnForwardFailure=yes', $command);
        self::assertStringContainsString('BatchMode=yes', $command);
        self::assertStringContainsString('ControlPath=', $command);
        self::assertStringContainsString('lm-docker-serwer.sock:/var/run/docker.sock', $command);
        self::assertStringContainsString("'anna@example.com'", $command);
        self::assertStringEndsWith('2>&1', $command);
        self::assertSame(TunnelStage::Starting, $service->state()->stage);
    }

    /**
     * Droga hasłowa (D102 nr 4): hasło idzie przez `SSH_ASKPASS`, **nigdy
     * wierszem polecenia** — wiersz widzi każdy proces w systemie.
     */
    public function testThePasswordTravelsThroughAskpassAndNeverTheCommandLine(): void
    {
        $processes = new StubBackgroundProcess();
        $service = SocketTunnelService::getInstance();
        $service->useSeam($processes);

        $service->open('serwer', 'anna@example.com', 22, '/var/run/docker.sock', 'sekretne-haslo');

        $command = $processes->startedCommands[0];

        self::assertStringContainsString('SSH_ASKPASS=', $command);
        self::assertStringContainsString('SSH_ASKPASS_REQUIRE=force', $command);
        self::assertStringContainsString('PreferredAuthentications=password,keyboard-interactive', $command);
        self::assertStringContainsString('PubkeyAuthentication=no', $command);
        self::assertStringContainsString('BatchMode=no', $command);
        self::assertStringNotContainsString('sekretne-haslo', $command, 'hasło nie stoi w wierszu polecenia');
        self::assertFalse(getenv('LM_SSH_PASSWORD'), 'zmienna znika zaraz po uruchomieniu potomka');
    }

    public function testASuccessfulMasterMeansTheTunnelStands(): void
    {
        $processes = new StubBackgroundProcess(pollsUntilDone: 1, output: '');
        $service = SocketTunnelService::getInstance();
        $service->useSeam($processes);

        $service->open('serwer', 'anna@example.com', 22, '/var/run/docker.sock');
        $service->advance();

        $state = $service->state();
        self::assertSame(TunnelStage::Up, $state->stage);
        self::assertStringEndsWith('lm-docker-serwer.sock', (string) $state->socketPath);
    }

    /** Powód niepowodzenia jest cytatem z klienta — jego ostatnim wierszem. */
    public function testAFailedMasterSaysWhyWithTheClientsLastLine(): void
    {
        $processes = new StubBackgroundProcess(
            pollsUntilDone: 1,
            output: "warning: something\nssh: connect to host example.com port 22: Connection refused",
            exitCode: 255,
        );
        $service = SocketTunnelService::getInstance();
        $service->useSeam($processes);

        $service->open('serwer', 'anna@example.com', 22, '/var/run/docker.sock');
        $service->advance();

        $state = $service->state();
        self::assertSame(TunnelStage::Failed, $state->stage);
        self::assertSame('module.docker.tunnel.rejected', $state->problemKey);
        self::assertSame(
            'ssh: connect to host example.com port 22: Connection refused',
            $state->problemParameters['reason'] ?? null,
        );
    }

    /**
     * Zamknięcie idzie `-O exit` przez gniazdo mistrza i **kasuje gniazdo
     * przywiezione** — `ssh` zostawia je po sobie, a gniazdo po nieżyjącym
     * tunelu wisi przy `connect()`.
     */
    public function testCloseTellsTheMasterToExitAndRemovesTheSocket(): void
    {
        $executed = [];
        $processes = new StubBackgroundProcess(pollsUntilDone: 1, output: '');
        $service = SocketTunnelService::getInstance();
        $service->useSeam($processes, function (string $command) use (&$executed): void {
            $executed[] = $command;
        });

        $service->open('serwer', 'anna@example.com', 22, '/var/run/docker.sock');
        $service->advance();

        // Gniazdo „zostawione przez ssh" — plik w miejscu, które zna usługa.
        $socket = SocketTunnelService::socketFor('serwer');
        touch($socket);

        $service->close();

        self::assertSame(TunnelStage::None, $service->state()->stage);
        self::assertFileDoesNotExist($socket, 'gniazdo przywiezione nie przeżywa zamknięcia');
        self::assertCount(1, $executed);
        self::assertStringContainsString("ssh -O 'exit'", $executed[0]);
        self::assertStringContainsString('lm-docker-serwer.ctl', $executed[0]);
    }

    /** Gniazdo leży w katalogu prywatnym: `XDG_RUNTIME_DIR`, a w jego braku `~/.light-manager`. */
    public function testTheSocketLandsInTheRuntimeDirectoryWhenItExists(): void
    {
        $runtime = getenv('XDG_RUNTIME_DIR');

        if (!is_string($runtime) || $runtime === '' || !is_dir($runtime)) {
            self::markTestSkipped('Maszyna bez XDG_RUNTIME_DIR — gałąź zapasową sprawdza test poniżej.');
        }

        self::assertSame(
            rtrim($runtime, '/') . '/lm-docker-serwer.sock',
            SocketTunnelService::socketFor('serwer'),
        );
    }

    public function testWithoutTheRuntimeDirectoryTheSocketFallsBackToHome(): void
    {
        $previousRuntime = getenv('XDG_RUNTIME_DIR');
        $previousHome = getenv('HOME');
        $home = sys_get_temp_dir() . '/lm-tunnel-' . getmypid() . '-' . random_int(1000, 9999);
        mkdir($home, 0o700, true);

        putenv('XDG_RUNTIME_DIR');
        putenv('HOME=' . $home);

        try {
            $socket = SocketTunnelService::socketFor('serwer');

            self::assertSame($home . '/.light-manager/lm-docker-serwer.sock', $socket);
            self::assertSame(0o700, fileperms($home . '/.light-manager') & 0o777);
        } finally {
            putenv($previousRuntime === false ? 'XDG_RUNTIME_DIR' : 'XDG_RUNTIME_DIR=' . $previousRuntime);
            putenv($previousHome === false ? 'HOME' : 'HOME=' . $previousHome);
            @rmdir($home . '/.light-manager');
            @rmdir($home);
        }
    }
}
