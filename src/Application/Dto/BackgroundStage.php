<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Etap pracy tłowej — cztery i ani jednego więcej.
 *
 * Odpowiednik `ChecksumStage` z kroku 25, i to nie przez naśladownictwo: praca
 * dłuższa od klatki ma zawsze te same cztery odsłony — nie zaczęto, trwa, jest
 * wynik, nie da się. Różnica między pracą własną a procesem potomnym leży
 * w tym, **co** stan niesie, a nie w tym, ile ma etapów.
 *
 * `Running` nie ma ułamka i to jest sedno różnicy: proces potomny nie mówi
 * o sobie nic, aż skończy, więc pasek postępu chodzi w trybie „nie wiadomo ile
 * jeszcze” (krok 23). Ułamek dopisany tu na siłę byłby licznikiem czasu udającym
 * postęp.
 */
enum BackgroundStage
{
    case Idle;

    case Running;

    case Done;

    case Failed;
}
