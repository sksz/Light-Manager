<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Lista wierszy z zaznaczeniem i suwakiem.
 *
 * Zaznaczenie to stonowana płaszczyzna z zaokrąglonymi rogami i krawędź
 * w akcencie po lewej — nie odwrócenie kolorów, które przy przewijaniu
 * migotało (krok 13). Kształt jest identyczny w każdym wierszu i zmienia się
 * wyłącznie jego położenie, więc renderer trzyma go w pamięci podręcznej jako
 * jedną bitmapę (krok 17, dźwignia 5).
 *
 * Lista dostaje **już wybrany** wycinek treści wraz z położeniem okna: sam
 * wybór robi `ScrollWindow`, bo wymaga pamięci między klatkami, a komponent
 * powstaje na nowo przy każdej z nich.
 */
final class ListView implements ComponentInterface
{
    /**
     * @param list<ListRow> $rows       widoczny wycinek listy
     * @param ?int          $selected   położenie zaznaczenia w tym wycinku
     * @param ?ScrollPosition $position okno przewijania; `null` — nie ma czego przewijać
     */
    public function __construct(
        private readonly array $rows,
        private readonly ?int $selected = null,
        private readonly ?ScrollPosition $position = null,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $primitives = [];

        foreach (array_slice($this->rows, 0, $bounds->rows) as $offset => $row) {
            $line = $bounds->line($offset);
            $role = $row->role;

            if ($offset === $this->selected) {
                foreach (Highlight::under($line) as $primitive) {
                    $primitives[] = $primitive;
                }

                $role = Role::SelectionText;
            }

            foreach ((new Label($row->left, $row->right, $role))->draw($line) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        if ($this->position !== null && $this->position->isNeeded()) {
            $primitives[] = new Scrollbar(
                new Rect($bounds->row, $bounds->right(), $bounds->rows, 1),
                $this->position,
            );
        }

        return $primitives;
    }
}
