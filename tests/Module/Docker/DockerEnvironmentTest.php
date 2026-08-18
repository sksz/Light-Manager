<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Domain\Exception\InvalidDockerEnvironmentException;
use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;
use LightManager\Module\Docker\Domain\ValueObject\EnvironmentKind;
use PHPUnit\Framework\TestCase;

/**
 * Samowalidacja wpisu środowiska (krok 58).
 *
 * Wzorce są wąskie, bo wartości wchodzą do nazwy pliku gniazda i do wiersza
 * polecenia — testy sprawdzają przede wszystkim to, czego cytowanie upilnować
 * nie może: **żadna wartość nie zaczyna się od myślnika**.
 */
final class DockerEnvironmentTest extends TestCase
{
    public function testALocalSocketEntryCarriesItsPath(): void
    {
        $entry = DockerEnvironment::localSocket('praca');

        self::assertSame(EnvironmentKind::LocalSocket, $entry->kind);
        self::assertSame('/var/run/docker.sock', $entry->socketPath);
        self::assertSame('/var/run/docker.sock', $entry->label());
    }

    public function testATunnelEntryShowsItsTargetInTheLabel(): void
    {
        $entry = DockerEnvironment::sshTunnel('serwer', 'anna@example.com', 2222);

        self::assertSame('anna@example.com:2222', $entry->label());
        self::assertSame('/var/run/docker.sock', $entry->socketPath, 'gniazdo zdalne ma wartość domyślną');
    }

    public function testATcpEntryShowsTheDaemonAddress(): void
    {
        $entry = DockerEnvironment::tcp('chmura', 'daemon.example.com', 2376, '/c/cert.pem', '/c/key.pem', '/c/ca.pem');

        self::assertSame('https://daemon.example.com:2376', $entry->label());
    }

    public function testIdentityIsTheName(): void
    {
        self::assertTrue(DockerEnvironment::localSocket('a')->equals(
            DockerEnvironment::sshTunnel('a', 'example.com'),
        ));
    }

    public function testANameWithShellCharactersIsRejected(): void
    {
        $this->expectException(InvalidDockerEnvironmentException::class);

        DockerEnvironment::localSocket('a;rm -rf');
    }

    public function testANameStartingWithADashIsRejected(): void
    {
        $this->expectException(InvalidDockerEnvironmentException::class);

        DockerEnvironment::localSocket('-oProxyCommand=x');
    }

    public function testATargetStartingWithADashIsRejected(): void
    {
        $this->expectException(InvalidDockerEnvironmentException::class);

        DockerEnvironment::sshTunnel('serwer', '-oProxyCommand=x');
    }

    public function testARelativeSocketPathIsRejected(): void
    {
        $this->expectException(InvalidDockerEnvironmentException::class);

        DockerEnvironment::localSocket('praca', 'docker.sock');
    }

    public function testARelativeCertificatePathIsRejected(): void
    {
        $this->expectException(InvalidDockerEnvironmentException::class);

        DockerEnvironment::tcp('chmura', 'example.com', 2376, 'cert.pem', '/c/key.pem', '/c/ca.pem');
    }

    public function testAPortOutsideTheRangeIsRejected(): void
    {
        $this->expectException(InvalidDockerEnvironmentException::class);

        DockerEnvironment::sshTunnel('serwer', 'example.com', 70000);
    }
}
