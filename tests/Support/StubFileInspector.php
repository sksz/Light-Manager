<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\FileInfo\Application\Port\FileInspectorPort;

/**
 * Narzędzie opisujące plik bez procesu potomnego.
 *
 * Port zszedł w kroku 20 z rdzenia do modułu `FileInfo`, więc podwójny zapamiętuje
 * dziś także to, z jakimi ustawieniami go zawołano — limit czasu i argumenty są
 * częścią umowy, a nie szczegółem implementacji.
 */
final class StubFileInspector implements FileInspectorPort
{
    /** @var list<string> */
    public array $inspectedPaths = [];

    /** @var list<array{int, string}> limit czasu i argumenty każdego wywołania */
    public array $options = [];

    public function __construct(
        private readonly string $description = 'ASCII text',
    ) {
    }

    public function describe(string $path, int $timeoutSeconds, string $arguments): string
    {
        $this->inspectedPaths[] = $path;
        $this->options[] = [$timeoutSeconds, $arguments];

        return $this->description;
    }
}
