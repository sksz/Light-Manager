<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Ssh\Application\HostBook;
use LightManager\Module\Ssh\Application\Port\HostBookPort;
use LightManager\Module\Ssh\Application\Port\LoadedHostBook;

/**
 * Książka hostów w pamięci — bo test nie ma prawa dotknąć `~/.light-manager/`.
 *
 * Ta sama zasada, co przy `StubPlaylistStorage` z kroku 45: plik w katalogu
 * domowym należy do użytkownika, a nie do przebiegu testów. Prawdziwej usłudze
 * (`SshStateService`) zostaje to, czego atrapą sprawdzić się nie da — zapis
 * przez plik tymczasowy i przeżywanie nieznanych kluczy — i to sprawdza się na
 * katalogu tymczasowym, nie na domowym.
 */
final class StubHostBook implements HostBookPort
{
    public int $saves = 0;

    /**
     * Zapamiętane katalogi zdalne (krok 49) — po jednym na wpis książki.
     *
     * Publiczne, bo test o nie pyta wprost: „ostatni katalog przeżywa ponowne
     * uruchomienie" sprawdza się tym, co atrapa dostała do zapamiętania.
     *
     * @var array<string, string>
     */
    public array $directories = [];

    public function __construct(
        private HostBook $book = new HostBook(),
        private readonly ?string $problemKey = null,
    ) {
    }

    public function load(): LoadedHostBook
    {
        return new LoadedHostBook($this->book, $this->problemKey);
    }

    public function save(HostBook $book): void
    {
        ++$this->saves;
        $this->book = $book;
    }

    public function location(): string
    {
        return '/tmp/light-manager-test/ssh.json';
    }

    public function lastDirectory(string $hostName): ?string
    {
        return $this->directories[$hostName] ?? null;
    }

    public function rememberDirectory(string $hostName, string $path): void
    {
        $this->directories[$hostName] = $path;
    }
}
