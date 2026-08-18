<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Domain\Exception;

use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;

/**
 * Wpis środowiska, którego nie da się przyjąć (krok 58).
 *
 * Stawka jest ta sama, co przy `HostProfile` z kroku 48, i wyższa niż przy
 * nazwie obrazu: nazwa wpisu wchodzi do **nazwy pliku gniazda** i do wiersza
 * polecenia `ssh`, cel tunelu — do wiersza polecenia, a ścieżki TLS do opcji
 * curla. Odsiew jest więc granicą między wpisanym napisem a poleceniem
 * uruchamianym przez powłokę.
 */
final class InvalidDockerEnvironmentException extends DomainException implements DescribesProblem
{
    /** @param array<string, string> $problemParameters */
    private function __construct(
        string $message,
        private readonly string $problemKey,
        private readonly array $problemParameters,
    ) {
        parent::__construct($message);
    }

    public static function forEmptyName(): self
    {
        return new self('Environment name is empty.', 'module.docker.env.problem.emptyName', []);
    }

    public static function forInvalidName(string $name): self
    {
        return new self(
            sprintf('Environment name "%s" carries characters that do not belong in one.', $name),
            'module.docker.env.problem.invalidName',
            ['name' => $name],
        );
    }

    public static function forInvalidSocketPath(string $path): self
    {
        return new self(
            sprintf('Socket path "%s" is not an absolute, printable path.', $path),
            'module.docker.env.problem.invalidSocket',
            ['path' => $path],
        );
    }

    public static function forInvalidTarget(string $target): self
    {
        return new self(
            sprintf('Tunnel target "%s" does not look like a host book entry or [user@]host.', $target),
            'module.docker.env.problem.invalidTarget',
            ['target' => $target],
        );
    }

    public static function forInvalidHost(string $host): self
    {
        return new self(
            sprintf('Daemon host "%s" is not a usable host name or address.', $host),
            'module.docker.env.problem.invalidHost',
            ['host' => $host],
        );
    }

    public static function forInvalidPort(int $port): self
    {
        return new self(
            sprintf('Port %d is outside the usable range.', $port),
            'module.docker.env.problem.invalidPort',
            ['port' => (string) $port],
        );
    }

    public static function forInvalidCertificatePath(string $path): self
    {
        return new self(
            sprintf('Certificate path "%s" is not an absolute, printable path.', $path),
            'module.docker.env.problem.invalidCertificate',
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
