<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Application\Command\CommandInput;
use LightManager\Module\Docker\Application\EnvironmentBook;
use LightManager\Module\Docker\Application\Environments;
use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;
use LightManager\Module\Docker\Presentation\Query\EnvironmentsQuery;
use LightManager\Tests\Support\StubContextCatalog;
use LightManager\Tests\Support\StubEnvironmentBook;
use LightManager\Tests\Support\StubTunnel;
use PHPUnit\Framework\TestCase;

/**
 * `docker.environments` (krok 58) — a przede wszystkim jej **granica**:
 * poświadczenia nie wychodzą wierszami (kryterium ukończenia kroku).
 */
final class EnvironmentsQueryTest extends TestCase
{
    public function testRowsCarryNeitherTlsPathsNorTheSshTarget(): void
    {
        $environments = new Environments(
            new StubEnvironmentBook(new EnvironmentBook([
                DockerEnvironment::sshTunnel('serwer', 'anna@tajny.example.com', 2222),
                DockerEnvironment::tcp('chmura', 'daemon.example.com', 2376, '/sekrety/cert.pem', '/sekrety/key.pem', '/sekrety/ca.pem'),
            ])),
            new StubContextCatalog(),
            new StubTunnel(),
        );

        $rows = (new EnvironmentsQuery($environments))->ask(new CommandInput())->rows();

        self::assertCount(3, $rows, 'dwa wpisy własne plus gniazdo lokalne');

        $flattened = json_encode($rows);
        self::assertIsString($flattened);
        self::assertStringNotContainsString('tajny.example.com', $flattened, 'cel SSH nie wychodzi');
        self::assertStringNotContainsString('/sekrety/', $flattened, 'ścieżki kluczy TLS nie wychodzą');

        self::assertSame('serwer', $rows[0]['name']);
        self::assertSame('tunnel', $rows[0]['kind']);
        self::assertSame('own', $rows[0]['origin']);
        self::assertSame('https://daemon.example.com:2376', $rows[1]['address'], 'adres demona nie jest poświadczeniem');
    }

    public function testTheCurrentTunnelRowCarriesTheTunnelStage(): void
    {
        $tunnel = new StubTunnel();
        $environments = new Environments(
            new StubEnvironmentBook(new EnvironmentBook([
                DockerEnvironment::sshTunnel('serwer', 'anna@example.com'),
            ])),
            new StubContextCatalog(),
            $tunnel,
        );
        $environments->select('serwer', 'anna@example.com', 22);
        $environments->tick();

        $rows = (new EnvironmentsQuery($environments))->ask(new CommandInput())->rows();

        self::assertTrue($rows[0]['current']);
        self::assertSame('up', $rows[0]['tunnel']);
        self::assertSame('', $rows[1]['tunnel'] ?? '', 'wiersz gniazda lokalnego milczy o tunelu');
    }

    /** Pokolenie bije przy zmianie wyboru i stanu tunelu — źródło umie powiedzieć, że się zmieniło. */
    public function testTheGenerationMovesWithTheChoiceAndTheTunnel(): void
    {
        $environments = new Environments(
            new StubEnvironmentBook(new EnvironmentBook([
                DockerEnvironment::sshTunnel('serwer', 'anna@example.com'),
            ])),
            new StubContextCatalog(),
            new StubTunnel(),
        );
        $query = new EnvironmentsQuery($environments);

        $before = $query->generation();
        $environments->select('serwer', 'anna@example.com', 22);
        $afterChoice = $query->generation();
        $environments->tick();
        $afterTunnel = $query->generation();

        self::assertGreaterThan($before, $afterChoice);
        self::assertGreaterThan($afterChoice, $afterTunnel, 'tunel stanął — odpowiedź się zmieniła');
    }
}
