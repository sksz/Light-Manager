<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Docker\Application\EnvironmentBook;
use LightManager\Module\Docker\Application\Port\EnvironmentBookPort;
use LightManager\Module\Docker\Application\Port\LoadedEnvironmentBook;

/**
 * Książka środowisk w pamięci (krok 58).
 *
 * Powód ten sam, co przy `StubHostBook`: test nie ma prawa czytać ani pisać
 * `~/.light-manager/docker.json` maszyny, na której akurat biegnie. Zapisy
 * liczą się, bo przebieg funkcjonalny sprawdza, że wybór środowiska trafia do
 * pliku — czyli że przeżyje uruchomienie.
 */
final class StubEnvironmentBook implements EnvironmentBookPort
{
    public int $saveCount = 0;

    /** Ostatnio zapisana książka — do zajrzenia po czynności. */
    public ?EnvironmentBook $saved = null;

    public function __construct(
        private EnvironmentBook $book = new EnvironmentBook(),
        private readonly ?string $problemKey = null,
    ) {
    }

    public function load(): LoadedEnvironmentBook
    {
        return new LoadedEnvironmentBook($this->book, $this->problemKey);
    }

    public function save(EnvironmentBook $book): void
    {
        ++$this->saveCount;
        $this->book = $book;
        $this->saved = $book;
    }

    public function location(): string
    {
        return '/tmp/docker.json';
    }
}
