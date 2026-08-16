<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Domain\Exception;

use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;

/**
 * Identyfikator kontenera, którego nie da się użyć (krok 51).
 *
 * Wyjątek **przedstawia się sam** (`DescribesProblem`), bo rdzeń nie wie, czym
 * jest kontener — ta sama zasada, co przy ścieżce zdalnej w kroku 49.
 *
 * Rzuca **wyłącznie samowalidacja obiektu wartości**, czyli miejsce, w którym
 * identyfikator powstaje z odpowiedzi demona albo z argumentu komendy.
 * Niepowodzenie zapytania nie rzuca nigdy (reguła 8): demon odmawia rutynowo
 * i wraca to kluczem w stanie pracy.
 */
final class InvalidContainerIdException extends DomainException implements DescribesProblem
{
    /** @param array<string, string> $problemParameters */
    private function __construct(
        string $message,
        private readonly string $problemKey,
        private readonly array $problemParameters,
    ) {
        parent::__construct($message);
    }

    public static function forEmptyId(): self
    {
        return new self('Container id is empty.', 'module.docker.id.empty', []);
    }

    public static function forMalformedId(string $value): self
    {
        return new self(
            sprintf('Container id "%s" is not hexadecimal.', $value),
            'module.docker.id.malformed',
            ['value' => $value],
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
