<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Application\DockerEndpoint;
use LightManager\Module\Docker\Infrastructure\DockerApiService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\TestCase;

/**
 * Odmowa rozmowy z punktem, z którym nie ma jak rozmawiać (krok 58).
 *
 * To jest mechanizm, na którym stoi kryterium „tunel, który nie wstał, kończy
 * się zdaniem, a nie pustą listą": pytanie zadane bez gotowego punktu wraca
 * **odmówioną rozmową z kluczem powodu**, lista bierze go do `problemKey`,
 * a ekran mówi zdaniem. Żaden test nie dotyka gniazda — odmowa pada, zanim
 * curl dostałby cokolwiek do roboty.
 */
final class DockerApiServiceTest extends TestCase
{
    use ResetsSingletons;

    protected function setUp(): void
    {
        if (!DockerApiService::hasCurl()) {
            self::markTestSkipped('Maszyna bez ext-curl — odmowę za rozszerzenie pokazuje moduł.');
        }

        $this->resetSingleton(DockerApiService::class);
    }

    protected function tearDown(): void
    {
        $this->resetSingleton(DockerApiService::class);
    }

    public function testANotReadyEndpointRefusesWithItsReason(): void
    {
        $service = DockerApiService::getInstance();
        $service->useEndpoint(DockerEndpoint::notReady('module.docker.tunnel.rejected', ['reason' => 'refused']));

        $result = $service->poll($service->get('/containers/json?all=1'));

        self::assertFalse($result->isDone());
        self::assertFalse($result->isRunning());
        self::assertSame('module.docker.tunnel.rejected', $result->problemKey);
        self::assertSame(['reason' => 'refused'], $result->problemParameters);
        self::assertFalse($service->isSupported());
    }

    public function testAMissingSocketRefusesWithItsPath(): void
    {
        $service = DockerApiService::getInstance();
        $service->useEndpoint(DockerEndpoint::unixSocket('/nie/ma/takiego.sock'));

        $result = $service->poll($service->get('/containers/json?all=1'));

        self::assertFalse($result->isDone());
        self::assertFalse($result->isRunning());
        self::assertSame('module.docker.env.socketMissing', $result->problemKey);
        self::assertSame(['path' => '/nie/ma/takiego.sock'], $result->problemParameters);
    }

    public function testATlsEndpointWithoutItsFilesRefuses(): void
    {
        $service = DockerApiService::getInstance();
        $service->useEndpoint(DockerEndpoint::tls('example.com', 2376, '/nie/ma/cert.pem', '/nie/ma/key.pem', '/nie/ma/ca.pem'));

        $result = $service->poll($service->get('/containers/json?all=1'));

        self::assertFalse($result->isDone());
        self::assertFalse($result->isRunning());
        self::assertSame('module.docker.env.certMissing', $result->problemKey);
    }
}
