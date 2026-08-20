<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Gdzie stoi pobieranie obrazu (krok 61, etap 3).
 *
 * **Krótszy od `PushStage` o jeden przystanek** i to jest cała różnica między
 * tymi dwiema pracami: wypchnięcie musi najpierw **oznaczyć** obraz nazwą
 * docelową, bo demon odmawia nazwie, której obraz lokalnie nie nosi. Pobranie
 * niczego nie oznacza — nazwa przychodzi z rejestru razem z treścią.
 */
enum PullStage
{
    case Idle;

    case Pulling;

    case Done;

    case Failed;
}
