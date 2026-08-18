<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Docker;

use LightManager\Module\Docker\Infrastructure\DockerContextReader;
use PHPUnit\Framework\TestCase;

/**
 * Rozbiór wypisu `docker context ls --format json` (krok 58).
 *
 * Rozbiór jest czysty i statyczny, więc sprawdza się go na próbkach bajtów —
 * jak `DockerJsonReader` i `ComposeListReader`. Próbka NDJSON pochodzi
 * z maszyny projektu (rozpoznanie D96); postać tablicowa ze starszych wydań
 * klienta jest sprawdzona osobno, bo różni się pierwszym znakiem.
 */
final class DockerContextReaderTest extends TestCase
{
    public function testNdjsonRowsBecomeContexts(): void
    {
        $contexts = DockerContextReader::parse(
            '{"Current":true,"Description":"Current DOCKER_HOST based configuration",'
            . '"DockerEndpoint":"unix:///var/run/docker.sock","Error":"","Name":"default"}' . "\n"
            . '{"Current":false,"Description":"","DockerEndpoint":"ssh://anna@example.com","Error":"","Name":"serwer"}' . "\n",
        );

        self::assertCount(2, $contexts);
        self::assertSame('default', $contexts[0]->name);
        self::assertTrue($contexts[0]->current);
        self::assertSame('/var/run/docker.sock', $contexts[0]->socketPath());
        self::assertSame('serwer', $contexts[1]->name);
        self::assertNull($contexts[1]->socketPath(), 'adres ssh:// nie jest gniazdem');
    }

    public function testAnArrayFromAnOlderClientIsReadWhole(): void
    {
        $contexts = DockerContextReader::parse(
            '[{"Current":true,"DockerEndpoint":"unix:///var/run/docker.sock","Name":"default"}]',
        );

        self::assertCount(1, $contexts);
        self::assertSame('default', $contexts[0]->name);
    }

    /** Wiersz nie do rozczytania wypada — nie unieważnia całości. */
    public function testABrokenLineFallsOutAndTheRestStays(): void
    {
        $contexts = DockerContextReader::parse(
            "to nie jest json\n"
            . '{"Current":false,"DockerEndpoint":"unix:///run/user/1000/docker.sock","Name":"rootless"}' . "\n"
            . '{"bez":"nazwy"}' . "\n",
        );

        self::assertCount(1, $contexts);
        self::assertSame('rootless', $contexts[0]->name);
    }

    public function testEmptyOutputMeansNoContexts(): void
    {
        self::assertSame([], DockerContextReader::parse(''));
    }
}
