<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Presentation\Component;

use LightManager\Application\Ui\Rect;
use LightManager\Module\Browser\Application\UseCase\PreviewSelectedEntryUseCase;
use LightManager\Module\Browser\Domain\Aggregate\Directory;
use LightManager\Presentation\Ui\Component\ImageBox;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Miniatura zaznaczonego pliku — policzona **dopiero przy rysowaniu**.
 *
 * Leniwość nie jest tu ozdobą, tylko odtworzeniem zachowania sprzed kroku 21.
 * `FrameComposer` pytał o podgląd po podziale okna i pomijał go, gdy pas nie
 * dostał ani jednego wiersza; ekran pytany o strefy **przed** podziałem nie ma jak
 * wiedzieć, czy pas w ogóle powstanie. Gdyby przypadek użycia biegł już
 * w `preview()`, okno niższe od progu pasa płaciłoby za odczyt nagłówka pliku,
 * którego i tak nikt nie zobaczy.
 *
 * Sam odczyt jest zapamiętywany po stronie przypadku użycia, więc wywołanie na
 * klatkę kosztuje porównanie klucza, a nie otwarcie pliku.
 */
final class PreviewBox implements ComponentInterface
{
    public function __construct(
        private readonly PreviewSelectedEntryUseCase $preview,
        private readonly Directory $directory,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $preview = $this->preview->execute($this->directory);

        if ($preview === null) {
            return [];
        }

        return (new ImageBox($preview->path, $preview->caption))->draw($bounds);
    }
}
