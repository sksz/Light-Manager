<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Config;

use LightManager\Infrastructure\Support\InfrastructureException;

/**
 * Awarie dostępu do pliku konfiguracyjnego.
 *
 * Wyjątek nie opuszcza `Infrastructure`: usługa łapie go w `save()` i zamienia
 * na przetłumaczony opis, który wraca portem. Istnieje mimo to, bo zapis idzie
 * przez cztery kroki (katalog, plik tymczasowy, treść, podmiana) i każdy z nich
 * potrafi się nie udać z innego powodu — bez wyjątku ta ścieżka byłaby
 * łańcuchem `if`-ów zwracających stringi.
 *
 * Komunikat jest techniczny i po angielsku; napis dla użytkownika składa usługa
 * z pola `failure` i ze ścieżki.
 */
final class ConfigException extends InfrastructureException
{
    private function __construct(
        public readonly ConfigFailure $failure,
        public readonly string $path,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forUnwritableDirectory(string $directory): self
    {
        return new self(
            ConfigFailure::UnwritableDirectory,
            $directory,
            sprintf('The settings directory "%s" could not be created.', $directory),
        );
    }

    public static function forUnwritableFile(string $path): self
    {
        return new self(
            ConfigFailure::UnwritableFile,
            $path,
            sprintf('The settings file "%s" could not be written.', $path),
        );
    }

    public static function forFailedEncoding(): self
    {
        return new self(
            ConfigFailure::FailedEncoding,
            '',
            'The contents of the settings file could not be built.',
        );
    }
}
