<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

use LightManager\Infrastructure\Support\InfrastructureException;

/**
 * Awarie trybu okienkowego. Wszystkie zatrzymują start aplikacji — wzorem
 * `TerminalException`: komunikat wyjątku jest techniczny i po angielsku,
 * a zdanie dla użytkownika składa `ProblemPresenter` po polu `problem`.
 */
final class GlfwException extends InfrastructureException
{
    private function __construct(
        public readonly GlfwProblem $problem,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function forMissingExtension(): self
    {
        return new self(
            GlfwProblem::MissingExtension,
            'The PHP extension "glfw" is unavailable; the windowed mode cannot start.',
        );
    }

    public static function forInitFailure(): self
    {
        return new self(
            GlfwProblem::InitFailure,
            'glfwInit() failed; no display available or GLFW misconfigured.',
        );
    }

    public static function forWindowFailure(): self
    {
        return new self(
            GlfwProblem::WindowFailure,
            'glfwCreateWindow() failed; an OpenGL 3.3 core context could not be created.',
        );
    }

    public static function forMissingFont(): self
    {
        return new self(
            GlfwProblem::MissingFont,
            'No monospace TTF font was found; the windowed renderer cannot draw text.',
        );
    }

    /**
     * Prośba o kontekst wektorowy po jego zwolnieniu — czyli po zamknięciu okna
     * (krok 39). Rodzaj awarii jest ten sam, co przy oknie, bo z punktu widzenia
     * użytkownika to jedno zdarzenie: okna nie ma. Nowego rodzaju nie zakładamy,
     * żeby nie dokładać zdania do katalogu napisów dla stanu, do którego
     * poprawny przebieg nie dochodzi.
     */
    public static function forReleasedContext(): self
    {
        return new self(
            GlfwProblem::WindowFailure,
            'The vector context was released together with the window; nothing may ask for it now.',
        );
    }
}
