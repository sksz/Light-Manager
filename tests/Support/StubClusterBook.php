<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Kubernetes\Application\ClusterBook;
use LightManager\Module\Kubernetes\Application\Port\ClusterBookPort;
use LightManager\Module\Kubernetes\Application\Port\LoadedClusterBook;

/**
 * Książka klastrów w pamięci (krok 59).
 *
 * Powód ten sam, co przy `StubHostBook` i `StubEnvironmentBook`: test nie ma
 * prawa czytać ani pisać dokumentu stanu maszyny, na której akurat biegnie.
 * Zapisy liczą się, bo przebieg funkcjonalny sprawdza, że wybór klastra trafia
 * do książki — czyli że przeżyje uruchomienie.
 */
final class StubClusterBook implements ClusterBookPort
{
    public int $saveCount = 0;

    /** Ostatnio zapisana książka — do zajrzenia po czynności. */
    public ?ClusterBook $saved = null;

    public function __construct(
        private ClusterBook $book = new ClusterBook(),
        private readonly ?string $problemKey = null,
        private readonly bool $fresh = false,
    ) {
    }

    public function load(): LoadedClusterBook
    {
        return new LoadedClusterBook($this->book, $this->problemKey, $this->fresh);
    }

    public function save(ClusterBook $book): void
    {
        ++$this->saveCount;
        $this->book = $book;
        $this->saved = $book;
    }

    public function location(): string
    {
        return '/tmp/state.json';
    }
}
