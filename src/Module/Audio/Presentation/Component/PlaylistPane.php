<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Presentation\Component;

use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\ComponentInterface;
use LightManager\Presentation\Ui\ScrollWindow;

/**
 * Playlista jako panel podziału (krok 46).
 *
 * Klasa powstała wyłącznie dlatego, że lista zamieszkała w **połowie** ekranu:
 * do kroku 45 rysował ją wprost `AudioScreen`, bo dostawała cały prostokąt.
 * Wewnątrz nie ma nic ponad `ListView` z kroku 18 — cała jej treść to wcięcie pod
 * obwódkę i przewinięcie okna, czyli dokładnie to, co po drugiej stronie podziału
 * robi `EffectList`.
 *
 * Wierszy **nie składa**: przychodzą gotowe, bo wiedza o tym, co gra i czego
 * brakuje, należy do odtwarzacza, a nie do rysowania.
 */
final class PlaylistPane implements ComponentInterface
{
    /** @param list<ListRow> $rows */
    public function __construct(
        private readonly array $rows,
        private readonly ScrollWindow $window,
        private readonly int $selected,
        private readonly bool $framed = false,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        $inner = $this->framed ? Panel::inner($bounds) : $bounds;

        if ($inner->isEmpty() || $this->rows === []) {
            return [];
        }

        $total = count($this->rows);
        $offset = $this->window->keepVisible($this->selected, $total, $inner->rows);

        return (new ListView(
            array_slice($this->rows, $offset, $inner->rows),
            $this->selected - $offset,
            $this->window->position($total, min($inner->rows, $total)),
        ))->draw($inner);
    }
}
