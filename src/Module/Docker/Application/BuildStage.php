<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Etap budowy obrazu (krok 51).
 *
 * Pięć wartości zamiast czterech, bo praca ma **dwa etapy o różnym koszcie**:
 * pakowanie jest miejscowe i policzalne, budowa dzieje się po stronie demona
 * i policzalna nie jest. Rozdzielenie ich w enumie jest tym, dzięki czemu pasek
 * postępu wie, kiedy pokazać ułamek, a kiedy przejść w tryb „postęp nieznany”.
 */
enum BuildStage
{
    case Idle;

    /** Kontekst pakuje się do archiwum — po kawałku, z policzalnym mianownikiem. */
    case Packing;

    /** Archiwum poszło do demona; czytamy strumień zdań o postępie. */
    case Building;

    case Done;
    case Failed;
}
