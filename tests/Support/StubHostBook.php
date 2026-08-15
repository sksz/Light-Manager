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
}
