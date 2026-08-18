<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Dokąd w tej chwili idzie rozmowa z demonem (krok 58).
 *
 * To jest to, co z wybranego wpisu zostaje po przekroczeniu portu: ścieżka
 * gniazda **albo** adres z trzema ścieżkami TLS, **albo** powód, dla którego
 * rozmowy nie ma jak zacząć (tunel jeszcze nie stoi, tunel nie wstał, wpis
 * klienta o adresie, którego moduł nie obsługuje). Usługa gniazda nie zna
 * wpisu ani książki — zna wyłącznie tę daną, i to jest cała stawka punktu 3
 * planu: ramkowanie logów, strumień budowy i `X-Registry-Auth` nie zmieniają
 * się o linię.
 */
final readonly class DockerEndpoint
{
    /** @param array<string, string> $problemParameters */
    private function __construct(
        public ?string $socketPath,
        public ?string $host,
        public int $port,
        public ?string $certPath,
        public ?string $keyPath,
        public ?string $caPath,
        public ?string $problemKey,
        public array $problemParameters,
    ) {
    }

    public static function unixSocket(string $socketPath): self
    {
        return new self($socketPath, null, 0, null, null, null, null, []);
    }

    public static function tls(string $host, int $port, string $certPath, string $keyPath, string $caPath): self
    {
        return new self(null, $host, $port, $certPath, $keyPath, $caPath, null, []);
    }

    /** @param array<string, string> $parameters */
    public static function notReady(string $problemKey, array $parameters = []): self
    {
        return new self(null, null, 0, null, null, null, $problemKey, $parameters);
    }

    public function isReady(): bool
    {
        return $this->problemKey === null;
    }

    public function isTls(): bool
    {
        return $this->host !== null;
    }

    /** Adres bazowy dla demona po sieci — bez wersji API, tę dokłada usługa. */
    public function baseUrl(): string
    {
        return 'https://' . ($this->host ?? '') . ':' . $this->port;
    }
}
