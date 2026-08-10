<?php

declare(strict_types=1);

namespace LightManager\Application\Ui;

/**
 * Rozmiar w wierszach i kolumnach siatki — odpowiedź komponentu na pytanie
 * „ile miejsca chcesz zająć”.
 *
 * Kontener pyta o rozmiar, zanim rozda prostokąty, bo dopiero wtedy wie, komu
 * ile zostawić. Odpowiedź jest życzeniem, nie zobowiązaniem: w niskim oknie
 * dziecko dostanie mniej, niż prosiło, i musi sobie z tym poradzić.
 */
final class Size
{
    public function __construct(
        public readonly int $rows,
        public readonly int $columns,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->rows === $other->rows && $this->columns === $other->columns;
    }
}
