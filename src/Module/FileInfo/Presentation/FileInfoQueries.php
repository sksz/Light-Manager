<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation;

use LightManager\Application\Query\QueryRegistry;
use LightManager\Domain\ValueObject\Preview;
use LightManager\Module\FileInfo\Application\Dto\ChecksumState;
use LightManager\Module\FileInfo\Application\Dto\DiskUsageState;
use LightManager\Module\FileInfo\Application\Dto\EntryDescription;
use LightManager\Module\FileInfo\Application\FileInfoSettings;

/**
 * Odczyt danych modułu opisu pliku — przez rejestr kwerend (krok 53, D92 nr 3).
 *
 * Ta sama fasada, co w module dźwięku, i ta sama reguła: rozpakowanie ładunku
 * stoi w jednym miejscu, a brak odpowiedzi jest zwykłym stanem. Dwie z czterech
 * kwerend oddają **stan pracy tłowej**, więc fasada nigdy nie czeka — oddaje to,
 * co jest w tej klatce.
 */
final readonly class FileInfoQueries
{
    public function __construct(
        private QueryRegistry $queries,
    ) {
    }

    public function description(): ?EntryDescription
    {
        $payload = $this->payloadOf('description');

        return $payload instanceof EntryDescription ? $payload : null;
    }

    public function preview(): ?Preview
    {
        $payload = $this->payloadOf('preview');

        return $payload instanceof Preview ? $payload : null;
    }

    public function checksum(): ChecksumState
    {
        $payload = $this->payloadOf('digest');

        return $payload instanceof ChecksumState ? $payload : ChecksumState::idle();
    }

    public function diskUsage(): DiskUsageState
    {
        $payload = $this->payloadOf('usage');

        return $payload instanceof DiskUsageState ? $payload : DiskUsageState::idle();
    }

    private function payloadOf(string $query): ?object
    {
        return $this->queries
            ->ask(FileInfoSettings::ID . '.' . $query)
            ->payloadFor(FileInfoSettings::ID);
    }
}
