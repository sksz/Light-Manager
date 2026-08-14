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

    /** Zapisz wzorcowe zrzuty wybranych scenariuszy (krok 38). */
    case ImageSave;

    /** Porównaj zrzuty wybranych scenariuszy z wzorcowymi (krok 38). */
    case ImageCompare;

    /** Zapisz złote klatki wybranych scenariuszy (krok 38). */
    case GoldenSave;

    case Help;
}
