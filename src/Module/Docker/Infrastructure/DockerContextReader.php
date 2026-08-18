<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Infrastructure;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundStage;
use LightManager\Application\Port\BackgroundProcessPort;
use LightManager\Infrastructure\Process\BackgroundProcessService;
use LightManager\Infrastructure\Support\AbstractSingleton;
use LightManager\Module\Docker\Application\ContextEntry;
use LightManager\Module\Docker\Application\Port\ContextCatalogPort;

/**
 * Konteksty klienta `docker` — praca tłowa i rozbiór NDJSON (krok 58).
 *
 * `docker context ls --format json` oddaje **po jednym obiekcie JSON
 * w wierszu** (sprawdzone na maszynie przy planowaniu, D96), więc rozbiór idzie
 * wiersz po wierszu, a wiersz nie do rozczytania **wypada, nie unieważnia
 * całości** — jak wpis książki nie do przyjęcia.
 *
 * **Strumieni nie scalamy** (reguła 15f) i jest to warunek poprawności, nie
 * porządku: wyjściem polecenia jest **treść** (JSON), a klient pisze na
 * strumieniu błędów ostrzeżenia. Sklejone dawałyby JSON nie do rozczytania —
 * i to nie zawsze, tylko wtedy, gdy akurat coś ostrzeże.
 *
 * **Brak klienta nie jest awarią** (D96 nr 3): odczyt nie zaczyna się w ogóle,
 * lista zostaje pusta, a spis środowisk schodzi do wpisów własnych plus gniazda
 * lokalnego.
 */
final class DockerContextReader extends AbstractSingleton implements ContextCatalogPort
{
    private const COMMAND = 'docker context ls --format json';

    /** Odczyt pliku lokalnego — limit jest strażnikiem, nie oczekiwaniem. */
    private const TIMEOUT_SECONDS = 10;

    /** @var list<ContextEntry> */
    private array $contexts = [];

    private ?BackgroundHandle $handle = null;

    private ?string $problemKey = null;

    private ?BackgroundProcessPort $processes = null;

    /** Podstawienie portu pracy tłowej — **wyłącznie dla testów** (wzorem compose). */
    public function useSeam(BackgroundProcessPort $processes): void
    {
        $this->processes = $processes;
    }

    public function refresh(): void
    {
        if ($this->handle !== null || !ComposeCliService::hasClient()) {
            return;
        }

        $this->problemKey = null;
        $this->handle = $this->processes()->start(self::COMMAND, self::TIMEOUT_SECONDS);
    }

    public function advance(): void
    {
        if ($this->handle === null) {
            return;
        }

        $result = $this->processes()->poll($this->handle);

        if ($result->stage === BackgroundStage::Running) {
            return;
        }

        $this->handle = null;

        if ($result->stage === BackgroundStage::Done && ($result->exitCode ?? 1) === 0) {
            $this->contexts = self::parse($result->output);

            return;
        }

        if ($result->stage !== BackgroundStage::Idle) {
            $this->problemKey = 'module.docker.env.contexts.failed';
        }
    }

    public function all(): array
    {
        return $this->contexts;
    }

    public function isReading(): bool
    {
        return $this->handle !== null;
    }

    public function problemKey(): ?string
    {
        return $this->problemKey;
    }

    /**
     * Rozbiór NDJSON — czysty i statyczny, więc testowalny bez procesu.
     *
     * Pola wedle sprawdzonego wypisu: `Name`, `DockerEndpoint`, `Current`.
     * Starsze wydania klienta oddają pod `--format json` **tablicę** zamiast
     * NDJSON — pierwszy wiersz zaczynający się od `[` czyta się wtedy w całości.
     *
     * @return list<ContextEntry>
     */
    public static function parse(string $output): array
    {
        $trimmed = ltrim($output);

        if (str_starts_with($trimmed, '[')) {
            /** @var mixed $decoded */
            $decoded = json_decode($trimmed, true);

            return is_array($decoded) ? self::entriesFrom($decoded) : [];
        }

        $items = [];

        foreach (explode("\n", $output) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            /** @var mixed $decoded */
            $decoded = json_decode($line, true);

            if (is_array($decoded)) {
                $items[] = $decoded;
            }
        }

        return self::entriesFrom($items);
    }

    /**
     * @param array<mixed> $items
     *
     * @return list<ContextEntry>
     */
    private static function entriesFrom(array $items): array
    {
        $entries = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $name = $item['Name'] ?? null;
            $endpoint = $item['DockerEndpoint'] ?? null;

            if (!is_string($name) || $name === '' || !is_string($endpoint)) {
                continue;
            }

            $entries[] = new ContextEntry($name, $endpoint, ($item['Current'] ?? false) === true);
        }

        return $entries;
    }

    private function processes(): BackgroundProcessPort
    {
        return $this->processes ?? BackgroundProcessService::getInstance();
    }
}
