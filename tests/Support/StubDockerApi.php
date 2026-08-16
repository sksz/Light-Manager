<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Docker\Application\DockerCall;
use LightManager\Module\Docker\Application\DockerResult;
use LightManager\Module\Docker\Application\Port\DockerApiPort;

/**
 * Demon Dockera bez demona (krok 51).
 *
 * Powód jest ten sam, co przy `StubAudio` i `StubSshSession`, i tu wyjątkowo
 * ostry: test rozmawiający z prawdziwym demonem **zmieniałby stan maszyny**, na
 * której akurat biegnie — zatrzymywał cudze kontenery, kasował cudze obrazy.
 * Żaden test tego kroku nie dotyka gniazda; kształt odpowiedzi sprawdza się na
 * próbkach bajtów (`DockerJsonReaderTest`), a zachowanie stanów — tutaj.
 *
 * Atrapa jest **kolejką odpowiedzi, nie serwerem**: mówi się jej z góry, co ma
 * oddać na kolejne pytania, i to wystarcza, bo stany pytają w kolejności, którą
 * test zna. Odpowiedź niezapowiedziana zostaje `Running` — czyli „jeszcze nie
 * doszło”, co jest w tym porcie stanem zwykłym.
 */
final class StubDockerApi implements DockerApiPort
{
    /** @var list<string> ścieżki, o które pytano — w kolejności */
    public array $paths = [];

    /** @var list<string> ścieżki pytań zmieniających (POST, DELETE) */
    public array $changes = [];

    public int $pumped = 0;

    public int $stopped = 0;

    /** @var array<int, DockerResult> odpowiedzi przypisane do uchwytów */
    private array $results = [];

    /** @var list<DockerResult> odpowiedzi czekające na kolejne pytania */
    private array $queue = [];

    private int $lastId = 0;

    /** Zapowiada odpowiedź na następne pytanie — kolejność zamówień jest kolejnością pytań. */
    public function willAnswer(DockerResult $result): self
    {
        $this->queue[] = $result;

        return $this;
    }

    /** Skrót na najczęstszy przypadek: udana odpowiedź z treścią. */
    public function willReturn(string $body, int $status = 200): self
    {
        return $this->willAnswer(DockerResult::done($body, $status));
    }

    public function get(string $path): DockerCall
    {
        return $this->begin($path);
    }

    public function post(string $path, ?string $body = null, ?string $contentType = null): DockerCall
    {
        $this->changes[] = 'POST ' . $path;

        return $this->begin($path);
    }

    public function delete(string $path): DockerCall
    {
        $this->changes[] = 'DELETE ' . $path;

        return $this->begin($path);
    }

    public function follow(string $path): DockerCall
    {
        return $this->begin($path);
    }

    public function poll(DockerCall $call): DockerResult
    {
        return $this->results[$call->id] ?? DockerResult::idle();
    }

    public function stop(DockerCall $call): void
    {
        ++$this->stopped;
        unset($this->results[$call->id]);
    }

    public function pump(): void
    {
        ++$this->pumped;
    }

    private function begin(string $path): DockerCall
    {
        $this->paths[] = $path;
        $call = new DockerCall(++$this->lastId);
        $this->results[$call->id] = array_shift($this->queue) ?? DockerResult::running();

        return $call;
    }
}
