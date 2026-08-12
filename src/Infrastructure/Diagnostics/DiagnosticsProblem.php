<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/**
 * Rodzaj awarii narzędzia pomiarowego.
 *
 * Wyjątek niesie ten enum w typowanym polu, a napis dla użytkownika dobierany
 * jest po nim — tą samą drogą, którą krok 15 wprowadził dla `TerminalProblem`
 * i `ConfigFailure` (D32). Komunikat samego wyjątku zostaje techniczny.
 */
enum DiagnosticsProblem: string
{
    case EmptySampleSet = 'emptySampleSet';
    case UnknownScenario = 'unknownScenario';
    case UnknownTheme = 'unknownTheme';
    case InvalidArgument = 'invalidArgument';
    case BaselineUnreadable = 'baselineUnreadable';
    case BaselineMissing = 'baselineMissing';
    case WriteFailed = 'writeFailed';
    case TerminalUnavailable = 'terminalUnavailable';
    case GlfwUnavailable = 'glfwUnavailable';

    public function textKey(): string
    {
        return 'bench.problem.' . $this->value;
    }
}
