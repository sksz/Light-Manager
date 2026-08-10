<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/** Co się dzieje z ekranem po obsłużeniu klawisza. */
enum Transition
{
    case Stay;
    case Close;
    case Quit;
}
