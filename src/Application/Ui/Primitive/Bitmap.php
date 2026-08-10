<?php

declare(strict_types=1);

namespace LightManager\Application\Ui\Primitive;

use LightManager\Application\Ui\Rect;

/**
 * Miejsce na obraz z pliku wraz z tym, co ma się w nim znaleźć, gdy obrazu nie
 * będzie.
 *
 * Prymityw niesie **ścieżkę**, nie piksele: wczytanie i przeskalowanie należy do
 * renderera, bo tylko on wie, ile pikseli ma komórka, i tylko on ma prawo
 * dotknąć Imagicka. Komponent nie zna wyniku tej próby z góry, więc podpis
 * zastępczy idzie razem ze ścieżką — inaczej pusta ramka wyglądałaby jak błąd
 * rysowania, a nie jak informacja.
 *
 * Tryb tekstowy nie rysuje bitmap w ogóle i zostaje przy samym podpisie. To
 * jedyny prymityw, przy którym degradacja nie jest uproszczeniem kształtu, lecz
 * rezygnacją z treści — i dlatego podpis musi wystarczać sam.
 */
final class Bitmap implements Primitive
{
    public function __construct(
        public readonly Rect $bounds,
        /** Ścieżka do pliku obrazu; `null`, gdy nie ma czego pokazywać. */
        public readonly ?string $path,
        public readonly string $caption,
    ) {
    }

    public function signature(): string
    {
        return 'I' . $this->bounds->signature() . ',' . ($this->path ?? '-') . ',' . $this->caption;
    }
}
