<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Module\Docker\Application\Port\RegistryPort;
use LightManager\Module\Docker\Application\Registry\RegistryCall;
use LightManager\Module\Docker\Application\Registry\RegistryEndpoint;
use LightManager\Module\Docker\Application\Registry\RegistryResult;

/**
 * Atrapa rozmowy z rejestrem obrazów (krok 61, etap 2).
 *
 * **Żaden przebieg nie rozmawia z prawdziwym rejestrem** i to jest reguła kroku,
 * a nie wygoda: cudzy serwer w testach znaczy przebieg zależny od sieci, od
 * cudzych limitów i od czyichś poświadczeń. Ścieżka jednoobiegowa jest
 * sprawdzona ręcznie na `registry:2` z celu `make registry-start`, a maszyna
 * trzech obiegów — testem jednostkowym `RegistryConversationTest`.
 *
 * Atrapa odpowiada **od razu**: opóźnienie sieciowe nie jest tu treścią, bo
 * etapy rozmowy sprawdza tamten test jednostkowy.
 */
final class StubRegistryApi implements RegistryPort
{
    public ?RegistryEndpoint $endpoint = null;

    /** Ile razy ustawiono punkt końcowy — liczba, bez której nie widać pytania co takt. */
    public int $endpointChanges = 0;

    /** @var list<string> ścieżki, o które pytano — w kolejności */
    public array $asked = [];

    private int $status = 200;

    private string $body = '{"repositories":[]}';

    private ?string $problemKey = null;

    private int $lastId = 0;

    /** @var array<int, RegistryResult> */
    private array $results = [];

    public function answer(int $status, string $body): void
    {
        $this->status = $status;
        $this->body = $body;
        $this->problemKey = null;
    }

    public function fail(string $problemKey): void
    {
        $this->problemKey = $problemKey;
    }

    public function useRegistry(RegistryEndpoint $endpoint): void
    {
        $this->endpoint = $endpoint;
        ++$this->endpointChanges;
    }

    public function catalog(): RegistryCall
    {
        return $this->begin('/v2/_catalog');
    }

    public function tags(string $image): RegistryCall
    {
        return $this->begin('/v2/' . $image . '/tags/list');
    }

    public function poll(RegistryCall $call): RegistryResult
    {
        return $this->results[$call->id] ?? RegistryResult::idle();
    }

    public function stop(RegistryCall $call): void
    {
        unset($this->results[$call->id]);
    }

    public function pump(): void
    {
    }

    private function begin(string $path): RegistryCall
    {
        $this->asked[] = $path;
        $call = new RegistryCall(++$this->lastId);
        $this->results[$call->id] = $this->problemKey === null
            ? RegistryResult::done($this->body, $this->status)
            : RegistryResult::failed($this->problemKey);

        return $call;
    }
}
