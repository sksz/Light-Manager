<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Etap pracy wtyczki compose (krok 51) — te same cztery, co przy rozmowie
 * z demonem i przy pracy tłowej rdzenia.
 *
 * Powtórzenie jest tu zamierzone i mieści się w granicy z reguły 15e: to
 * **pojęcie dziedziny** (etap pracy), a nie mechanizm rdzenia. Mechanizm —
 * proces potomny — moduł bierze z rdzenia i nie podrabia go ani w jednej linii.
 */
enum ComposeStage
{
    case Idle;
    case Working;
    case Done;
    case Failed;
}
