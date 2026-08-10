<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

/**
 * Rodzaj awarii terminala.
 *
 * Istnieje, bo `Presentation` dobiera napis dla użytkownika po klasie wyjątku,
 * a `TerminalException` opisuje cztery różne kłopoty. Rozbicie jej na cztery
 * klasy dałoby ten sam efekt kosztem czterech plików; typowane pole niesie tę
 * samą informację i zostaje danymi, a nie kluczem katalogu napisów.
 */
enum TerminalProblem
{
    case NonInteractiveStdin;
    case MissingPcntl;
    case DisabledExec;
    case SttyFailure;
}
