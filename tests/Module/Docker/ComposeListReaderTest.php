<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Infrastructure\ComposeListReader;
use PHPUnit\Framework\TestCase;

/**
 * Wypis wtyczki compose — **na zapisanym JSON-ie, nigdy przez `docker compose`**
 * (krok 51).
 *
 * Próbka pochodzi z prawdziwego wywołania `docker compose ls -a --format json`
 * (v2.29.7) i niesie osobliwość, której nikt nie zgadłby z dokumentacji:
 * **`ConfigFiles` bywa ścieżką względną** — taką, jaką podano przy podnoszeniu
 * projektu. Dla kogoś, kto stoi w innym katalogu, jest ona bezużyteczna, więc
 * czytnik oddaje ją bez zmian, a rozstrzyga o niej ten, kto jej użyje.
 */
final class ComposeListReaderTest extends TestCase
{
    private const JSON = '[{"Name":"dev","Status":"exited(8)","ConfigFiles":"docker/dev/docker-compose.yaml"},'
        . '{"Name":"development","Status":"running(15)","ConfigFiles":"/home/kto/projekt/compose.yml"}]';

    public function testProjectsCarryNameStatusAndFile(): void
    {
        $projects = ComposeListReader::projects(self::JSON);

        self::assertCount(2, $projects);
        self::assertSame('dev', $projects[0]->name);
        self::assertFalse($projects[0]->isRunning());
        self::assertSame('docker/dev/docker-compose.yaml', $projects[0]->configPath);
        self::assertTrue($projects[1]->isRunning());
    }

    /** Projekt złożony z kilku plików niesie je **rozdzielone przecinkiem**. */
    public function testOnlyTheFirstConfigFileIsKept(): void
    {
        $projects = ComposeListReader::projects(
            '[{"Name":"x","Status":"running(1)","ConfigFiles":"compose.yaml,compose.override.yaml"}]',
        );

        self::assertSame('compose.yaml', $projects[0]->configPath);
    }

    /** Pusty spis jest zwykłą odpowiedzią: nic nie działa. */
    public function testAnEmptyListIsNotAProblem(): void
    {
        self::assertSame([], ComposeListReader::projects('[]'));
        self::assertSame([], ComposeListReader::projects('to nie jest JSON'));
    }

    /**
     * **Powód odmowy to ostatni niepusty wiersz**, a nie pierwszy.
     *
     * Wtyczka wypisuje najpierw ostrzeżenia (wersja pliku, nieużywane zmienne),
     * a dopiero na końcu to, przez co się poddała — pierwszy wiersz podałby
     * użytkownikowi ostrzeżenie w miejscu powodu.
     */
    public function testTheReasonIsTheLastLineOfTheErrorStream(): void
    {
        $errorOutput = "WARN[0000] /x/compose.yml: `version` is obsolete\n"
            . "no configuration file provided: not found\n";

        self::assertSame('no configuration file provided: not found', ComposeListReader::reason($errorOutput));
    }

    /** Wtyczka, która się nie odezwała, dostaje nazwę zamiast pustego zdania. */
    public function testASilentFailureStillNamesItsSource(): void
    {
        self::assertSame('docker compose', ComposeListReader::reason(''));
    }
}
