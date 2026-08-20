<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Domain\Exception;

use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;

/**
 * Adres rejestru, którym nie da się nazwać obrazu (krok 61).
 *
 * Przedstawia się sam (reguła 8, krok 21): rdzeń nie ma prawa znać nazw modułu,
 * więc wyjątek niesie klucz katalogu i parametry, a nie napis.
 */
final class InvalidRegistryAddressException extends DomainException implements DescribesProblem
{
    private function __construct(
        public readonly string $address,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forAddress(string $address): self
    {
        return new self($address, sprintf('Invalid image registry address: "%s".', $address));
    }

    public function problemKey(): string
    {
        // Klucz wpisany wprost, a nie sklejony z `DockerSettings::ID`: `Domain`
        // nie sięga do `Application` (reguła warstw), a sąsiedzi w tym katalogu
        // robią tak samo.
        return 'module.docker.registry.invalidAddress';
    }

    public function problemParameters(): array
    {
        return ['address' => $this->address];
    }
}
