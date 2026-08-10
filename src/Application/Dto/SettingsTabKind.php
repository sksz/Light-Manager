<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Czym jest zakładka ekranu ustawień — a przez to, jak rdzeń rysuje jej wnętrze.
 *
 * Trzy rodzaje, bo trzy są naprawdę różne. `Core` i `Module` różnią się
 * wyłącznie tym, skąd biorą pozycje (enum `SettingKey` kontra deklaracja
 * `ModuleSetting`) i obie kończą się tym samym komponentem. `Modules` jest
 * przypadkiem osobnym: jej wiersze nie są ustawieniami modułu, tylko spisem
 * samych modułów, a wiersz modułu odrzuconego nie jest ustawieniem w ogóle —
 * niesie powód odrzucenia i **nie da się go przełączyć**.
 */
enum SettingsTabKind
{
    case Core;
    case Module;
    case Modules;
}
