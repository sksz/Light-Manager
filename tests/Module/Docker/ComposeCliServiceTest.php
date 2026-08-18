<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Application\ComposeAction;
use LightManager\Module\Docker\Infrastructure\ComposeCliService;
use LightManager\Tests\Support\ResetsSingletons;
use LightManager\Tests\Support\StubBackgroundProcess;
use PHPUnit\Framework\TestCase;

/**
 * Wiersz polecenia compose z przedrostkiem środowiska (krok 58).
 *
 * Usługa dostaje port pracy tłowej szwem, więc żaden test nie uruchamia
 * klienta Dockera. Sprawdza się samą umowę: przedrostek `DOCKER_HOST=…` stoi
 * **przed** poleceniem — także przy `ls`, bo spis projektów z innego demona
 * niż kontenery byłby drugą prawdą o tej samej maszynie.
 */
final class ComposeCliServiceTest extends TestCase
{
    use ResetsSingletons;

    protected function setUp(): void
    {
        $this->resetSingleton(ComposeCliService::class);
    }

    protected function tearDown(): void
    {
        $this->resetSingleton(ComposeCliService::class);
    }

    public function testTheEnvironmentPrefixStandsBeforeTheCommand(): void
    {
        $processes = new StubBackgroundProcess();
        $service = ComposeCliService::getInstance();
        $service->useSeam($processes);
        $service->useEnvironment("DOCKER_HOST='unix:///run/user/1000/lm-docker-serwer.sock' ");

        $service->begin(ComposeAction::ListProjects);

        self::assertSame(
            "DOCKER_HOST='unix:///run/user/1000/lm-docker-serwer.sock' docker compose ls -a --format json",
            $processes->startedCommands[0] ?? null,
        );
    }

    public function testWithoutAPrefixTheCommandStaysAsItWas(): void
    {
        $processes = new StubBackgroundProcess();
        $service = ComposeCliService::getInstance();
        $service->useSeam($processes);

        $service->begin(ComposeAction::Up, '/projekt/compose.yaml');

        self::assertSame(
            "docker compose -f '/projekt/compose.yaml' up -d",
            $processes->startedCommands[0] ?? null,
        );
    }
}
