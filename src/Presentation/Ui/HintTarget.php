<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Ui\Rect;

/**
 * Podpowiedź stopki wraz z prostokątem, w którym ją narysowano (krok 55).
 *
 * Jest to **jedyna mapa trafień w rdzeniu** i jej granica jest ostra: dotyczy
 * paska stanu, czyli jedynej rzeczy, którą rdzeń w klatce rysuje sam. Miara
 * kroku 55 mówi, że rdzeń nie ma prawa wiedzieć, gdzie leży **wiersz listy** —
 * i nie wie: treść stref rysuje ekran i to on pamięta swoje prostokąty
 * (`AcceptsPointer`). Kto rysuje, ten pamięta; rdzeń rysuje stopkę.
 */
final class HintTarget
{
    public function __construct(
        public readonly Rect $bounds,
        public readonly KeyBinding $binding,
    ) {
    }

    /**
     * Wiązanie pod wskaźnikiem albo `null`.
     *
     * @param list<self> $targets
     */
    public static function at(array $targets, PointerEvent $event): ?KeyBinding
    {
        foreach ($targets as $target) {
            if ($event->hits($target->bounds)) {
                return $target->binding;
            }
        }

        return null;
    }
}
