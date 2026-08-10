<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

use LightManager\Infrastructure\Support\InfrastructureException;

/**
 * Awarie dostępu do terminala. Wszystkie zatrzymują start aplikacji, więc
 * użytkownik zobaczy je na wyjściu błędów — ale przetłumaczone, złożone przez
 * `Presentation` z rodzaju awarii i szczegółu (krok 15). Sam komunikat wyjątku
 * jest techniczny i po angielsku.
 */
final class TerminalException extends InfrastructureException
{
    private function __construct(
        public readonly TerminalProblem $problem,
        /** Szczegół awarii — dziś wypełnia go wyłącznie nieudane `stty`. */
        public readonly string $detail,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forNonInteractiveStdin(): self
    {
        return new self(
            TerminalProblem::NonInteractiveStdin,
            '',
            'Standard input is not a terminal; an interactive session is required.',
        );
    }

    public static function forMissingPcntl(): self
    {
        return new self(
            TerminalProblem::MissingPcntl,
            '',
            'The PHP extension "pcntl" is unavailable; the terminal cannot be restored on a signal.',
        );
    }

    public static function forDisabledExec(): self
    {
        return new self(
            TerminalProblem::DisabledExec,
            '',
            'exec() is disabled; "stty" cannot be called to switch the terminal to raw mode.',
        );
    }

    public static function forSttyFailure(string $arguments, int $exitCode, string $message): self
    {
        $detail = sprintf(
            'stty %s: exit code %d, %s',
            $arguments,
            $exitCode,
            $message === '' ? 'no message' : $message,
        );

        return new self(TerminalProblem::SttyFailure, $detail, $detail);
    }
}
