<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Dto\PointerEvent;
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
        $primitives = [];

        foreach (self::placements($this->labels, $bounds) as $index => $placement) {
            $primitives[] = new TextRun(
                $bounds->row,
                $placement->column,
                $this->labels[$index],
                $this->roleFor($index),
            );
        }

        return $primitives;
    }

    /**
     * Prostokąty kolejnych zakładek — **ten sam rachunek, co rysowanie**
     * (krok 55).
     *
     * Statyczne i tutaj, a nie u wołającego, z tego samego powodu, dla którego
     * `StatusBar` oddaje prostokąty swoich podpowiedzi: odstęp między
     * zakładkami i próg, za którym pasek się urywa, są własnością **tego**
     * komponentu. Drugi rachunek u wołającego rozjechałby się przy pierwszej
     * zmianie `GAP_COLUMNS`, a rozjazd byłby niewidoczny do chwili, gdy ktoś
     * kliknie i trafi w sąsiednią zakładkę.
     *
     * @param list<string> $labels
     *
     * @return array<int, Rect> numer zakładki → jej prostokąt; zakładki, które
     *                          się nie zmieściły, w wyniku nie stoją
     */
    public static function placements(array $labels, Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $placements = [];
        $column = $bounds->column;
        $limit = $bounds->column + $bounds->columns;

        foreach ($labels as $index => $label) {
            $width = mb_strlen($label);

            if ($column + $width > $limit) {
                break;
            }

            $placements[$index] = new Rect($bounds->row, $column, 1, $width);
            $column += $width + self::GAP_COLUMNS;
        }

        return $placements;
    }

    /**
     * Numer zakładki pod wskaźnikiem albo `null`.
     *
     * @param list<string> $labels
     */
    public static function at(array $labels, Rect $bounds, PointerEvent $event): ?int
    {
        foreach (self::placements($labels, $bounds) as $index => $placement) {
            if ($event->hits($placement)) {
                return $index;
            }
        }

        return null;
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
