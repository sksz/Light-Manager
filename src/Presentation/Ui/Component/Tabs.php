<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Pasek zakładek: nazwy w jednym wierszu, aktywna wyróżniona kolorem.
 *
 * Do kroku 18 aktywną zakładkę oznaczały nawiasy kwadratowe wokół napisu —
 * `[ Wygląd ]` — bo wiersz klatki nie potrafił powiedzieć „ten fragment jest
 * wyróżniony”. Nawiasy przesuwały przy okazji resztę paska, więc zmiana
 * zakładki poruszała cały wiersz. Prymityw niesie rolę koloru osobno dla
 * każdego napisu, więc wyróżnienie nic już nie przesuwa.
 */
final class Tabs implements ComponentInterface
{
    private const GAP_COLUMNS = 3;

    /** @param list<string> $labels */
    public function __construct(
        private readonly array $labels,
        private readonly int $active,
        /** Czy kursor stoi na samym pasku, a nie na pozycji pod nim. */
        private readonly bool $focused = false,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $primitives = [];
        $column = $bounds->column;
        $limit = $bounds->column + $bounds->columns;

        foreach ($this->labels as $index => $label) {
            $width = mb_strlen($label);

            if ($column + $width > $limit) {
                break;
            }

            $primitives[] = new TextRun($bounds->row, $column, $label, $this->roleFor($index));
            $column += $width + self::GAP_COLUMNS;
        }

        return $primitives;
    }

    /**
     * Aktywna zakładka jest w akcencie, pozostałe w tonie stonowanym. Gdy kursor
     * stoi na pasku, aktywna przechodzi na kolor tekstu zaznaczenia — inaczej
     * nie dałoby się odróżnić „patrzysz na zakładkę” od „patrzysz na pozycję
     * wewnątrz niej”.
     */
    private function roleFor(int $index): Role
    {
        if ($index !== $this->active) {
            return Role::Muted;
        }

        return $this->focused ? Role::SelectionText : Role::Accent;
    }
}
