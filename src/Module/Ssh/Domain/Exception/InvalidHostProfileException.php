<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Domain\Exception;

use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;

/**
 * Profil hosta, którego nie da się zapisać w książce (krok 48).
 *
 * Wyjątek **przedstawia się sam** (`DescribesProblem`), bo mówi o dziedzinie
 * modułu: rdzeń nie wie, czym jest host, i nie ma prawa dobierać dla niego
 * zdania po klasie (reguła 8, D42).
 *
 * **Samowalidacja profilu jest tu warstwą bezpieczeństwa, nie porządkiem.**
 * Nazwa hosta i nazwa użytkownika trafiają do wiersza polecenia uruchamianego
 * przez powłokę, więc pilnują ich dwie rzeczy naraz: `escapeshellarg()` po
 * stronie usługi i te wzorce tutaj. Osobno pilnowany jest **pierwszy znak** —
 * wartość zaczynająca się od `-` byłaby dla `ssh` opcją, a nie hostem, i żadne
 * cytowanie powłoki przed tym nie chroni, bo powłoka nie jest tu problemem.
 */
final class InvalidHostProfileException extends DomainException implements DescribesProblem
{
    /** @param array<string, string> $problemParameters */
    private function __construct(
        string $message,
        private readonly string $problemKey,
        private readonly array $problemParameters,
    ) {
        parent::__construct($message);
    }

    public static function invalidName(string $name): self
    {
        return new self(
            sprintf('Host profile name "%s" contains control characters or is too long.', $name),
            'module.ssh.profile.name.invalid',
            ['name' => $name],
        );
    }

    public static function invalidHost(string $host): self
    {
        return new self(
            sprintf('Host "%s" is not a usable host name or address.', $host),
            'module.ssh.profile.host.invalid',
            ['host' => $host],
        );
    }

    public static function invalidUser(string $user): self
    {
        return new self(
            sprintf('User "%s" is not a usable login name.', $user),
            'module.ssh.profile.user.invalid',
            ['user' => $user],
        );
    }

    public static function invalidPort(int $port): self
    {
        return new self(
            sprintf('Port %d is outside 1..65535.', $port),
            'module.ssh.profile.port.invalid',
            ['port' => (string) $port],
        );
    }

    public static function invalidKeyPath(string $path): self
    {
        return new self(
            sprintf('Key path "%s" is not an absolute path.', $path),
            'module.ssh.profile.key.invalid',
            ['path' => $path],
        );
    }

    public function problemKey(): string
    {
        return $this->problemKey;
    }

    public function problemParameters(): array
    {
        return $this->problemParameters;
    }
}
