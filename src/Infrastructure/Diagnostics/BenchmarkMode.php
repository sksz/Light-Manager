<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

/** Co narzędzie ma zrobić w tym uruchomieniu. */
enum BenchmarkMode
{
    /** Zmierz scenariusze, wypisz tabelę, opcjonalnie zapisz wzorzec i porównaj. */
    case Run;

    /** Zapisz płótno scenariusza do PNG i nic nie mierz. */
    case Snapshot;

    case Help;
}
