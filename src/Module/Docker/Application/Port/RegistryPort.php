<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Port;

use LightManager\Module\Docker\Application\Registry\RegistryCall;
use LightManager\Module\Docker\Application\Registry\RegistryEndpoint;
use LightManager\Module\Docker\Application\Registry\RegistryResult;

/**
 * Rozmowa z rejestrem obrazów — **druga rozmowa HTTP tego modułu i pierwsza
 * z siecią** (krok 61, etap 2).
 *
 * Kontrakt jest bliźniaczy wobec `DockerApiPort` i to jest tu treścią, a nie
 * stylem: reguła nadrzędna Fazy XVII brzmi **żadne wywołanie sieciowe nie pada
 * w rysowaniu klatki**, więc port mówi o **pracy**, a nie o wyniku — zaczyna ją
 * (`catalog()`, `tags()`), posuwa raz na takt (`pump()`) i oddaje stan
 * (`poll()`). Metody zwracającej gotową odpowiedź nie ma i nie będzie.
 *
 * **Trzy obiegi chowają się za tym kontraktem w całości.** Wołający zamawia
 * katalog raz; czy rejestr odpowiedział od razu, czy odesłał po token
 * i wywołanie trzeba było powtórzyć, widać wyłącznie po `RegistryStage`
 * w `poll()` — i widać po to, żeby dało się powiedzieć, na co się czeka, a nie
 * po to, żeby wołający cokolwiek z tym robił.
 */
interface RegistryPort
{
    /** Z którym rejestrem rozmawiamy — wolno zmienić między wywołaniami. */
    public function useRegistry(RegistryEndpoint $endpoint): void;

    /**
     * Katalog rejestru (`GET /v2/_catalog`).
     *
     * **Rozszerzenie opcjonalne**: specyfikacja OCI go nie wymaga, więc `404`
     * jest zwykłą odpowiedzią, a nie awarią — czyta się ją `RegistryResult::isMissing()`.
     */
    public function catalog(): RegistryCall;

    /** Etykiety jednego obrazu (`GET /v2/<nazwa>/tags/list`). */
    public function tags(string $image): RegistryCall;

    public function poll(RegistryCall $call): RegistryResult;

    public function stop(RegistryCall $call): void;

    /** Posunięcie wszystkich rozmów o tyle, ile da się bez czekania. */
    public function pump(): void;
}
