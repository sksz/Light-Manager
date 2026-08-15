<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Port\TrashPort;
use LightManager\Domain\Exception\FileOperationException;

/**
 * Kosz, który dysku nie dotyka (krok 44) — z tego samego powodu, co
 * `StubFileOperations`: ścieżki katalogów w pamięci bywają prawdziwe na
 * maszynie testowej, a test stopki nie ma prawa dosypać niczego do kosza
 * osoby, która go uruchamia. Przebiegi sprawdzające **sam kosz** biorą
 * prawdziwą usługę i podstawiony katalog tymczasowy.
 *
 * Atrapa przyjmuje wszystko (`accepts()` — `true`), pamięta, co „przeniesiono”,
 * i umie oddać to przy przywracaniu; kolizje rozwiązuje sufiksem, jak
 * pierwowzór.
 */
final class StubTrash implements TrashPort
{
    /** @var list<string> ślad wywołań: `trash:/a/b`, `restore:nazwa`, `reserve:/a/b` */
    public array $performed = [];

    /** @var array<string, string> nazwa w koszu → ścieżka powrotna */
    public array $stored = [];

    /** Powód, którym każda czynność ma się skończyć — `null` znaczy „udaje się”. */
    public ?FileOperationException $failWith = null;

    /** Ścieżki, których `accepts()` ma odmówić — droga pytania o inny system plików. */
    public function __construct(
        /** @var list<string> */
        public array $foreign = [],
    ) {
    }

    public function defaultDirectory(): string
    {
        return '/stub/Trash';
    }

    public function accepts(string $path, string $trashDirectory): bool
    {
        return !in_array($path, $this->foreign, true);
    }

    public function moveToTrash(string $path, string $trashDirectory): string
    {
        $this->performed[] = 'trash:' . $path;
        $this->fail();

        $name = $this->free(basename($path));
        $this->stored[$name] = $path;

        return $name;
    }

    public function reserve(string $path, string $trashDirectory): string
    {
        $this->performed[] = 'reserve:' . $path;
        $this->fail();

        $name = $this->free(basename($path));
        $this->stored[$name] = $path;

        return $name;
    }

    public function releaseUnused(array $names, string $trashDirectory): array
    {
        // Atrapa nie rozróżnia rezerwacji od wpisu — wszystko, co zna, „stoi”.
        return array_values(array_filter($names, fn (string $name): bool => isset($this->stored[$name])));
    }

    public function restore(string $trashName, string $trashDirectory): string
    {
        $this->performed[] = 'restore:' . $trashName;
        $this->fail();

        $path = $this->stored[$trashName] ?? null;

        if ($path === null) {
            throw FileOperationException::missing($trashDirectory . '/files/' . $trashName);
        }

        unset($this->stored[$trashName]);

        return $path;
    }

    private function fail(): void
    {
        if ($this->failWith !== null) {
            $problem = $this->failWith;
            $this->failWith = null;

            throw $problem;
        }
    }

    private function free(string $base): string
    {
        if (!isset($this->stored[$base])) {
            return $base;
        }

        for ($attempt = 1; ; ++$attempt) {
            $candidate = $base . '.' . $attempt;

            if (!isset($this->stored[$candidate])) {
                return $candidate;
            }
        }
    }
}
