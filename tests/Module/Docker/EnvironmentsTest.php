<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Application\ContextEntry;
use LightManager\Module\Docker\Application\EnvironmentBook;
use LightManager\Module\Docker\Application\EnvironmentOrigin;
use LightManager\Module\Docker\Application\Environments;
use LightManager\Module\Docker\Application\TunnelStage;
use LightManager\Module\Docker\Domain\ValueObject\DockerEnvironment;
use LightManager\Tests\Support\StubContextCatalog;
use LightManager\Tests\Support\StubEnvironmentBook;
use LightManager\Tests\Support\StubTunnel;
use PHPUnit\Framework\TestCase;

/**
 * Koordynator środowisk (krok 58): dwa źródła jednej listy, wybór bieżącego
 * i punkt końcowy rozmowy.
 *
 * Trzy reguły z D96 nr 3 mają tu po teście: pochodzenie jest widoczne, przy
 * zbieżnej nazwie wygrywa wpis własny (kolizja zostaje w spisie), a brak
 * klienta `docker` nie jest awarią — lista schodzi do wpisów własnych plus
 * gniazda lokalnego.
 */
final class EnvironmentsTest extends TestCase
{
    public function testWithoutAClientTheListFallsBackToTheLocalSocket(): void
    {
        $environments = self::environments();

        $rows = $environments->rows();

        self::assertCount(1, $rows);
        self::assertSame(EnvironmentBook::DEFAULT_NAME, $rows[0]->name);
        self::assertSame(EnvironmentOrigin::Default, $rows[0]->origin);
        self::assertTrue($rows[0]->current);
        self::assertSame('/var/run/docker.sock', $environments->endpoint()->socketPath);
    }

    public function testAnOwnEntryShadowsTheClientContextOfTheSameName(): void
    {
        $contexts = new StubContextCatalog([
            new ContextEntry('default', 'unix:///var/run/docker.sock', true),
            new ContextEntry('serwer', 'ssh://anna@example.com', false),
        ]);
        $environments = self::environments(
            new StubEnvironmentBook(new EnvironmentBook([
                DockerEnvironment::sshTunnel('serwer', 'anna@example.com'),
            ])),
            $contexts,
        );
        $environments->refresh();
        $environments->tick();

        $rows = $environments->rows();

        self::assertCount(3, $rows, 'wpis własny, dwa konteksty — kolizja widoczna, nie znika');
        self::assertSame(EnvironmentOrigin::Own, $rows[0]->origin);
        self::assertFalse($rows[0]->shadowed);

        $shadowed = array_values(array_filter($rows, static fn ($row): bool => $row->shadowed));
        self::assertCount(1, $shadowed);
        self::assertSame('serwer', $shadowed[0]->name);
        self::assertSame(EnvironmentOrigin::Client, $shadowed[0]->origin);
    }

    public function testSelectingATunnelEntryOpensTheTunnelAndSwitches(): void
    {
        $book = new StubEnvironmentBook(new EnvironmentBook([
            DockerEnvironment::sshTunnel('serwer', 'anna@example.com', 2222, '/run/docker.sock'),
        ]));
        $tunnel = new StubTunnel(advancesUntilDone: 2);
        $environments = self::environments($book, tunnel: $tunnel);

        $problem = $environments->select('serwer', 'anna@example.com', 2222);

        self::assertNull($problem);
        self::assertSame(['serwer anna@example.com 2222 /run/docker.sock'], $tunnel->opened);
        self::assertTrue($environments->takeSwitched());
        self::assertFalse($environments->takeSwitched(), 'znacznik jest zabierany, nie oglądany');
        self::assertSame('serwer', $environments->currentName());
        self::assertSame('serwer', $book->saved?->current(), 'wybór przeżywa uruchomienie');

        // Tunel dopiero wstaje — punkt końcowy mówi dlaczego, zamiast podawać
        // gniazdo, którego jeszcze nie ma.
        $environments->tick();
        self::assertSame('module.docker.tunnel.waiting', $environments->endpoint()->problemKey);

        // Tunel stanął — rozmowa idzie przywiezionym gniazdem.
        $environments->tick();
        self::assertSame(TunnelStage::Up, $environments->tunnel()->stage);
        self::assertStringEndsWith('.sock', (string) $environments->endpoint()->socketPath);
    }

    public function testATunnelThatDidNotRiseHasItsOwnSentence(): void
    {
        $book = new StubEnvironmentBook(new EnvironmentBook([
            DockerEnvironment::sshTunnel('serwer', 'anna@example.com'),
        ]));
        $environments = self::environments(
            $book,
            tunnel: new StubTunnel(problemKey: 'module.docker.tunnel.rejected'),
        );

        $environments->select('serwer', 'anna@example.com', 22);
        $environments->tick();
        $environments->tick();

        $endpoint = $environments->endpoint();
        self::assertFalse($endpoint->isReady());
        self::assertSame('module.docker.tunnel.rejected', $endpoint->problemKey);
    }

    public function testATcpEntryYieldsATlsEndpointAndComposeVariables(): void
    {
        $book = new StubEnvironmentBook(new EnvironmentBook([
            DockerEnvironment::tcp('chmura', 'daemon.example.com', 2376, '/c/cert.pem', '/c/key.pem', '/c/ca.pem'),
        ]));
        $environments = self::environments($book);

        $environments->select('chmura');

        $endpoint = $environments->endpoint();
        self::assertTrue($endpoint->isTls());
        self::assertSame('https://daemon.example.com:2376', $endpoint->baseUrl());
        self::assertSame('/c/cert.pem', $endpoint->certPath);

        $prefix = $environments->composePrefix();
        self::assertStringContainsString("DOCKER_HOST='tcp://daemon.example.com:2376'", $prefix);
        self::assertStringContainsString('DOCKER_TLS_VERIFY=1', $prefix);
        self::assertStringContainsString("DOCKER_CERT_PATH='/c'", $prefix);
        self::assertTrue($environments->isRemote());
    }

    public function testTheLocalSocketPrefixesComposeWithItsPath(): void
    {
        $environments = self::environments();

        self::assertSame("DOCKER_HOST='unix:///var/run/docker.sock' ", $environments->composePrefix());
        self::assertFalse($environments->isRemote());
    }

    public function testAClientContextWithAnUnusableAddressRefusesTheChoice(): void
    {
        $contexts = new StubContextCatalog([
            new ContextEntry('serwer', 'ssh://anna@example.com', false),
        ]);
        $environments = self::environments(contexts: $contexts);
        $environments->refresh();
        $environments->tick();

        self::assertSame('module.docker.env.problem.unusableContext', $environments->select('serwer'));
        self::assertFalse($environments->takeSwitched());
    }

    public function testAClientContextCannotBeRemoved(): void
    {
        $contexts = new StubContextCatalog([
            new ContextEntry('default', 'unix:///var/run/docker.sock', true),
        ]);
        $book = new StubEnvironmentBook();
        $environments = self::environments($book, $contexts);
        $environments->refresh();
        $environments->tick();

        self::assertFalse($environments->remove('default'));
        self::assertSame(0, $book->saveCount, 'odmowa nie dotyka pliku');
    }

    public function testRemovingTheCurrentEntryFallsBackToTheDefault(): void
    {
        $book = new StubEnvironmentBook(new EnvironmentBook([
            DockerEnvironment::localSocket('praca', '/run/praca.sock'),
        ]));
        $environments = self::environments($book);

        $environments->select('praca');
        self::assertTrue($environments->remove('praca'));

        self::assertSame(EnvironmentBook::DEFAULT_NAME, $environments->currentName());
    }

    private static function environments(
        ?StubEnvironmentBook $book = null,
        ?StubContextCatalog $contexts = null,
        ?StubTunnel $tunnel = null,
    ): Environments {
        return new Environments(
            $book ?? new StubEnvironmentBook(),
            $contexts ?? new StubContextCatalog(),
            $tunnel ?? new StubTunnel(),
        );
    }
}
