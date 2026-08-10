<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Container;

use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Szczelina w kontenerze: dziecko wraz z tym, ile miejsca chce, ile mu
 * wystarczy i jak chętnie ustępuje, gdy miejsca brakuje.
 *
 * Trzy liczby zamiast jednej, bo podział okna w tej aplikacji nie jest
 * proporcjonalny. Drabinka z kroku 13 mówi wprost, **w jakiej kolejności**
 * strefy mają ustępować w niskim oknie: najpierw znika pas podglądu, potem
 * obwódki, a lista dostaje zawsze co najmniej jeden wiersz. Kontener, który
 * umie tylko dzielić po równo albo po udziałach, nie odtworzyłby tego nigdy.
 *
 * Miary są bezwymiarowe — wiersze w kontenerze pionowym, kolumny w poziomym.
 * Ta sama szczelina opisuje więc oba kierunki, a kontener nadaje jej znaczenie.
 */
final class Slot
{
    public function __construct(
        public readonly ComponentInterface $child,
        /** Poniżej tylu miar dziecko nie ma sensu; 0 znaczy „może zniknąć”. */
        public readonly int $minimum,
        /** Ile chce dostać, gdy jest z czego dawać; `null` — „resztę”. */
        public readonly ?int $preferred,
        /** Kolejność ustępowania: im mniejsza, tym wcześniej oddaje miejsce. */
        public readonly int $yieldOrder = 0,
    ) {
    }

    /** Szczelina elastyczna — bierze wszystko, co zostanie po pozostałych. */
    public static function flexible(ComponentInterface $child, int $minimum = 1): self
    {
        return new self($child, $minimum, null, PHP_INT_MAX);
    }

    /** Szczelina o stałej mierze, ustępująca w podanej kolejności. */
    public static function fixed(ComponentInterface $child, int $size, int $yieldOrder = 0): self
    {
        return new self($child, 0, $size, $yieldOrder);
    }
}
