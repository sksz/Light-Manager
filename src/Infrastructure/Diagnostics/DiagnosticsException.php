<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Infrastructure\Support\InfrastructureException;

/**
 * Awaria narzędzia pomiarowego.
 *
 * Komunikat jest techniczny i po angielsku; napis dla użytkownika dobiera
 * `BenchmarkCli` po polu `$problem`, a konkret (nazwa scenariusza, ścieżka
 * pliku) bierze z pola `$detail` — nie z rozbierania treści komunikatu.
 */
final class DiagnosticsException extends InfrastructureException
{
    private function __construct(
        string $message,
        public readonly DiagnosticsProblem $problem,
        public readonly string $detail = '',
    ) {
        parent::__construct($message);
    }

    public static function forEmptySampleSet(): self
    {
        return new self(
            'Cannot aggregate an empty set of samples.',
            DiagnosticsProblem::EmptySampleSet,
        );
    }

    public static function forUnknownScenario(string $name): self
    {
        return new self(
            sprintf('Unknown benchmark scenario "%s".', $name),
            DiagnosticsProblem::UnknownScenario,
            $name,
        );
    }

    public static function forUnknownTheme(string $name): self
    {
        return new self(
            sprintf('Unknown theme "%s".', $name),
            DiagnosticsProblem::UnknownTheme,
            $name,
        );
    }

    public static function forInvalidArgument(string $argument): self
    {
        return new self(
            sprintf('Invalid command line argument "%s".', $argument),
            DiagnosticsProblem::InvalidArgument,
            $argument,
        );
    }

    public static function forUnreadableBaseline(string $path): self
    {
        return new self(
            sprintf('Baseline file "%s" is not valid benchmark JSON.', $path),
            DiagnosticsProblem::BaselineUnreadable,
            $path,
        );
    }

    public static function forMissingBaseline(string $path): self
    {
        return new self(
            sprintf('No baseline file found at "%s".', $path),
            DiagnosticsProblem::BaselineMissing,
            $path,
        );
    }

    public static function forFailedWrite(string $path): self
    {
        return new self(
            sprintf('Could not write "%s".', $path),
            DiagnosticsProblem::WriteFailed,
            $path,
        );
    }

    public static function forUnavailableTerminal(): self
    {
        return new self(
            'Transfer phase needs an interactive terminal on both STDIN and STDOUT.',
            DiagnosticsProblem::TerminalUnavailable,
        );
    }

    public static function forUnavailableGlfw(): self
    {
        return new self(
            'The PHP extension "glfw" is unavailable; the windowed benchmark cannot run.',
            DiagnosticsProblem::GlfwUnavailable,
        );
    }
}
