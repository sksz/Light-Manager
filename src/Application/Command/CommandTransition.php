<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

/** Co po komendzie dzieje się z oknem komend i z aplikacją. */
enum CommandTransition
{
    /** Okno się zamyka — tak kończy się większość wywołań. */
    case Close;

    /** Okno zostaje otwarte: komenda ma coś do powiedzenia, a wiersz do poprawienia. */
    case Stay;

    case Quit;
}
