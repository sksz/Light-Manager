<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

use LightManager\Application\Command\CommandInterface;

/**
 * Moduł, który wnosi własne komendy do okna komend z kroku 19.
 *
 * Zdolność mieści się w `Application` obok `ProvidesSettingsTab`, bo kontrakt
 * komendy leży tam samo (`Application\Command`) i nie zna ani jednego typu
 * z `Presentation` — ekran do otwarcia wskazuje identyfikatorem (D39, P24).
 *
 * **Przestrzeni nazw pilnuje rejestr komend**, dokładnie tak samo, jak katalog
 * napisów pilnuje przedrostka `module.<id>.`: nazwa spoza przestrzeni właściciela
 * zostaje odrzucona wraz z powodem, a nie wpuszczona pod cudzym przedrostkiem.
 */
interface ProvidesCommands
{
    /** @return list<CommandInterface> nazwy muszą zaczynać się od `<id>.` */
    public function commands(): array;
}
