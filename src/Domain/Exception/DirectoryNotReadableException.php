<?php

declare(strict_types=1);

namespace LightManager\Domain\Exception;

use LightManager\Domain\ValueObject\DirectoryPath;

/**
 * Najczęstszy wyjątek widoczny dla użytkownika: katalog bez prawa odczytu albo
 * zniknięty spod nóg. Ścieżka jest tu polem, bo `Presentation` wstawia ją do
 * przetłumaczonego komunikatu.
 */
final class DirectoryNotReadableException extends DomainException
{
    private function __construct(
        public readonly string $path,
    ) {
        parent::__construct(sprintf('Directory "%s" cannot be read.', $path));
    }

    public static function forPath(DirectoryPath $path): self
    {
        return new self($path->value);
    }
}
