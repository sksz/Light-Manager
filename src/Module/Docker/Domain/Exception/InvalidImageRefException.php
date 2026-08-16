<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Domain\Exception;

use LightManager\Domain\Exception\DescribesProblem;
use LightManager\Domain\Exception\DomainException;

/**
 * Wskazanie obrazu, którego nie da się użyć (krok 51).
 *
 * Powód istnienia jest ostrzejszy niż przy identyfikatorze kontenera: nazwę
 * obrazu **wpisuje człowiek** — przy budowaniu podaje się ją z ręki — a ta sama
 * nazwa wchodzi potem do ścieżki żądania. Odsiew jest więc granicą między
 * wpisanym napisem a adresem, pod który pytamy demona.
 */
final class InvalidImageRefException extends DomainException implements DescribesProblem
{
    /** @param array<string, string> $problemParameters */
    private function __construct(
        string $message,
        private readonly string $problemKey,
        private readonly array $problemParameters,
    ) {
        parent::__construct($message);
    }

    public static function forEmptyReference(): self
    {
        return new self('Image reference is empty.', 'module.docker.image.empty', []);
    }

    public static function forMalformedReference(string $value): self
    {
        return new self(
            sprintf('Image reference "%s" carries characters that do not belong in one.', $value),
            'module.docker.image.malformed',
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
