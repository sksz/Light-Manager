<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application;

/**
 * Na czym stoi odczyt zdalnego katalogu (krok 49).
 *
 * Etapów jest cztery, a nie sześć jak w sesji, i to jest miara tego, ile
 * odwrócenie drogi technicznej fazy (D87) uprościło ten krok. Plan przewidywał
 * odczyt **dwustopniowy** — najpierw nazwy, potem atrybuty widocznego okna po
 * jednym obiegu na wpis — czyli dwa etapy pracy trwającej i budżet kawałka
 * mierzony zegarem. Sprawdzenie na żywym serwerze pokazało, że `sftp ls -l`
 * oddaje **nazwę razem z atrybutami w jednym obiegu**, więc drugi stopień nie ma
 * czego dobierać.
 */
enum ListingStage
{
    /** Nic nie zamówiono albo poprzedni odczyt sprzątnięto. */
    case Idle;

    /** Potomek pracuje; lista jeszcze nie przyszła. */
    case Listing;

    /** Katalog jest — i to jest jedyny etap, w którym wolno go rysować. */
    case Ready;

    /** Nie udało się; powód stoi w stanie kluczem katalogu napisów. */
    case Failed;

    public function isWorking(): bool
    {
        return $this === self::Listing;
    }
}
