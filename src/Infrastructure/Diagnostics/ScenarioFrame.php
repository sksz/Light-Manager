<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use LightManager\Application\Ui\Frame;

/**
 * Treść jednej mierzonej klatki wraz z siatką, na której powstała — dokładnie
 * to, co enkoder dostaje na wejściu w aplikacji, tyle że złożone bez systemu
 * plików i bez pętli gry.
 *
 * Siatka jest tu osobno, bo od kroku 18 klatka nie niesie już układu: prostokąty
 * prymitywów są w komórkach, a przeliczeniem ich na piksele zajmuje się
 * renderer, któremu trzeba podać, ile tych komórek jest.
 */
final class ScenarioFrame
{
    public function __construct(
        public readonly Frame $frame,
        public readonly int $rows,
        public readonly int $columns,
    ) {
    }
}
