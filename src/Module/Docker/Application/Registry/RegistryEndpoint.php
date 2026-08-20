<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Registry;

/**
 * Z którym rejestrem rozmawiamy i czym się przedstawiamy (krok 61, etap 2).
 *
 * Rodzeństwo `DockerEndpoint` i stoi tu z tego samego powodu: **kod rozmowy nie
 * ma się zmieniać o linię**, gdy zmienia się rozmówca (D96 nr 2). Token jest
 * **daną tego obiektu**, a nie ustawieniem usługi — powstaje przy każdym
 * wywołaniu, z wpisu książki, i ginie razem z nim; własnego magazynu tokenów
 * nie ma i mieć nie będzie (poza zakresem kroku).
 */
final readonly class RegistryEndpoint
{
    public function __construct(
        /** Host z opcjonalnym portem — `ghcr.io`, `localhost:5000`. */
        public string $address,
        public string $user = '',
        public string $token = '',
        /** Rejestr w sieci lokalnej: rozmowa idzie `http`, nie `https`. */
        public bool $insecure = false,
    ) {
    }

    /**
     * Adres bazowy API v2 — bez ukośnika na końcu.
     *
     * @return non-empty-string
     */
    public function baseUrl(): string
    {
        return ($this->insecure ? 'http://' : 'https://') . $this->address;
    }

    /** Czy jest czym się przedstawić w drugim obiegu. */
    public function hasCredentials(): bool
    {
        return $this->user !== '' || $this->token !== '';
    }
}
