<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

use LightManager\Application\Query\QueryInterface;

/**
 * Moduł, który oddaje swoje dane rejestrowi kwerend (krok 53).
 *
 * Zdolność mieści się w `Application` obok `ProvidesCommands`, bo kontrakt
 * kwerendy leży tam samo (`Application\Query`) i nie zna ani jednego typu
 * z `Presentation` — kryterium podziału z D38 P2.
 *
 * **Przestrzeni nazw pilnuje rejestr kwerend**, dokładnie tak samo, jak rejestr
 * komend pilnuje nazw czynności: nazwa spoza przestrzeni właściciela zostaje
 * odrzucona wraz z powodem, a nie wpuszczona pod cudzym przedrostkiem.
 */
interface ProvidesQueries
{
    /** @return list<QueryInterface> nazwy muszą zaczynać się od `<id>.` */
    public function queries(): array;
}
