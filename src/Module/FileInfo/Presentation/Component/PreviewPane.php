<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Presentation\Component;

use LightManager\Application\Port\TranslatorPort;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;
use LightManager\Module\FileInfo\Presentation\FileInfoQueries;
use LightManager\Module\FileInfo\Presentation\FileInfoState;
use LightManager\Presentation\Ui\Component\ImageBox;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\TextView;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Prawy panel opisu: miniatura, **treść pliku tekstowego** albo zdanie,
 * dlaczego nie ma ani jednego.
 *
 * Trzecia odpowiedź weszła w kroku 29 i wchodzi **tą samą drogą, co pierwsze
 * dwie**: wybór należy do modułu, a komponent rdzenia dostaje gotową treść.
 * Kolejność pytań jest rozstrzygnięciem — obraz przed tekstem, bo `.svg` jest
 * jednym i drugim naraz, a w panelu podglądu chce się widzieć rysunek, nie jego
 * zapis XML.
 *
 * Podgląd liczy się **dopiero przy rysowaniu**, tak samo jak w przeglądarce od
 * kroku 21, i od kroku 29 ma to drugi, mocniejszy powód: odczyt zależy od
 * geometrii panelu, a tę zna wyłącznie `draw()`. Ekran pytany o strefy przed
 * podziałem okna nie wie jeszcze ani czy prawy panel powstanie, ani jak będzie
 * szeroki.
 *
 * Komponent leży w katalogu modułu, bo zna jego stan — a `ImageBox` i `TextView`,
 * których używa, należą do rdzenia i nie wiedzą o pliku nic.
 */
final class PreviewPane implements ComponentInterface
{
    public function __construct(
        private readonly FileInfoState $state,
        private readonly FileInfoQueries $queries,
        private readonly TranslatorPort $translator,
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $preview = $this->queries->preview();

        if ($preview !== null) {
            return (new ImageBox($preview->path, $preview->caption))->draw($bounds);
        }

        // Szerokość **treści**, a nie panelu: od poprawki z 2026-08-12 przewijanie
        // liczy się w linijkach, a linijka jest tym, co się mieści obok suwaka
        // i kolumny numerów. Regułę trzyma `TextView`, żeby czytający plik i
        // rysujący go liczyli ją tak samo. Suwak zakładamy — podgląd bez niego to
        // plik krótszy od panelu, czyli ten, którego i tak nie ma jak przewijać.
        $columns = TextView::contentColumns($bounds, true, $this->state->textFirstNumber(), $bounds->rows);
        $window = $this->state->textWindow($bounds->rows, $columns);

        if ($window === null) {
            return $this->sentence('module.file-info.preview.none', $bounds);
        }

        if ($window->problemKey !== null) {
            return $this->sentence($window->problemKey, $bounds);
        }

        return (new TextView(
            $window->lines,
            $this->state->textWrap(),
            $window->scroll(),
            $this->state->textFirstNumber(),
        ))->draw($bounds);
    }

    /** @return list<Primitive> */
    private function sentence(string $key, Rect $bounds): array
    {
        return (new Label($this->translator->translate($key)))->draw($bounds);
    }
}
