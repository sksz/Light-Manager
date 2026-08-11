<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Dto;

/**
 * Etap liczenia zajętości katalogu — cztery odsłony wiersza „zajęte na dysku”.
 *
 * Enum jest własnością modułu, choć wygląda jak `BackgroundStage` z rdzenia,
 * i nie jest to powielenie przez nieuwagę. Rdzeń mówi o **procesie**: ruszył,
 * trwa, skończył się z takim a takim kodem. Moduł mówi o **liczbie**: nie ma
 * jej jeszcze, liczy się, jest, nie będzie. Kod wyjścia `du` nie jest tym samym,
 * co niepowodzenie pomiaru — polecenie kończy się jedynką za każdy katalog,
 * którego nie przeczytało, a mimo to podaje sumę tego, co przeczytać zdołało.
 */
enum DiskUsageStage
{
    case Idle;

    case Running;

    case Done;

    case Failed;
}
