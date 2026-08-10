<?php

declare(strict_types=1);

namespace LightManager\Domain\ValueObject;

use LightManager\Domain\Exception\InvalidScrollPositionException;

/**
 * Położenie okna przewijania na liście wpisów.
 *
 * Klatka niosła dotąd wyłącznie widoczny wycinek listy, więc renderer nie miał
 * z czego narysować suwaka — nie wiedział ani ile wpisów zostało poza ekranem,
 * ani z której strony. Te trzy liczby to wszystko, czego potrzeba; przeliczenie
 * ich na piksele szyny należy już do renderera.
 */
final class ScrollPosition
{
    public function __construct(
        public readonly int $first,
        public readonly int $visible,
        public readonly int $total,
    ) {
        foreach (['first' => $first, 'visible' => $visible, 'total' => $total] as $name => $value) {
            if ($value < 0) {
                throw InvalidScrollPositionException::forNegativeValue($name, $value);
            }
        }

        if ($first + $visible > $total) {
            throw InvalidScrollPositionException::forWindowOutsideList($first, $visible, $total);
        }
    }

    /** Suwak ma sens dopiero wtedy, gdy część listy została poza oknem. */
    public function isNeeded(): bool
    {
        return $this->total > $this->visible && $this->visible > 0;
    }

    /** Udział widocznego wycinka w całej liście — wysokość suwaka na szynie. */
    public function visibleFraction(): float
    {
        return $this->total === 0 ? 1.0 : $this->visible / $this->total;
    }

    /**
     * Położenie suwaka jako ułamek drogi, którą ma do przebycia: 0.0 na
     * początku listy, 1.0 na jej końcu. Dzielimy przez liczbę wpisów
     * *niewidocznych*, więc dojazd do końca listy zawsze dosuwa suwak do dołu
     * szyny — niezależnie od tego, ile wpisów mieści się w oknie.
     */
    public function progress(): float
    {
        $hidden = $this->total - $this->visible;

        return $hidden <= 0 ? 0.0 : $this->first / $hidden;
    }

    public function equals(self $other): bool
    {
        return $this->first === $other->first
            && $this->visible === $other->visible
            && $this->total === $other->total;
    }
}
