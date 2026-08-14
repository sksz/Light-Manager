<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Desktop;

use LightManager\Infrastructure\Support\InfrastructureException;

/**
 * Awarie zakładania wpisu pulpitu (krok 37).
 *
 * Komunikat jest techniczny i po angielsku, jak w całej hierarchii
 * `InfrastructureException`; napis dla użytkownika składa `bin/install-desktop-entry`
 * z pola `failure` i ze ścieżki.
 */
final class DesktopException extends InfrastructureException
{
    private function __construct(
        public readonly DesktopFailure $failure,
        public readonly string $path,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forUnwritableDirectory(string $directory): self
    {
        return new self(
            DesktopFailure::UnwritableDirectory,
            $directory,
            sprintf('The directory "%s" could not be created.', $directory),
        );
    }

    public static function forUnwritableFile(string $path): self
    {
        return new self(
            DesktopFailure::UnwritableFile,
            $path,
            sprintf('The file "%s" could not be written.', $path),
        );
    }
}
