<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

/** Rodzaj awarii okna GLFW — po nim `ProblemPresenter` dobiera zdanie dla użytkownika. */
enum GlfwProblem
{
    case MissingExtension;
    case InitFailure;
    case WindowFailure;
    case MissingFont;
}
