<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Glfw;

use GL\VectorGraphics\VGContext;
use LightManager\Infrastructure\Support\AbstractSingleton;

/**
 * Kontekst grafiki wektorowej rozszerzenia (NanoVG na GL3) wraz z fontem
 * i komórką siatki policzoną z jego metryk (krok 35, D54).
 *
 * Usługa wymaga bieżącego kontekstu OpenGL, więc w sekwencji bootstrapu stoi
 * za `GlfwWindowService` — i to ona zdejmuje z kroku 34 komórkę zastępczą:
 * szerokość komórki wychodzi z szerokości znaku fontu o stałej szerokości,
 * wysokość — z proporcji pisma do wiersza wspólnej z torem sixelowym
 * (`GlfwFrameMetrics::FONT_SIZE_RATIO`). Dzięki temu wiersze i kolumny
 * viewportu, rozmiar startowy okna i tekst w klatce mówią o tej samej komórce.
 *
 * Atlas glifów utrzymuje NanoVG wewnętrznie (font stash) — rasteryzacja
 * glifu płaci się raz na parę znak×rozmiar, nie raz na klatkę; to jest
 * okienny odpowiednik pamięci bitmap napisów z kroku 17.
 */
final class VgContextService extends AbstractSingleton
{
    /** Nazwa, pod którą font jest zarejestrowany w kontekście. */
    public const FONT_NAME = 'mono';

    /**
     * Rozmiar pisma, z którego liczy się komórka aplikacji. Sam rendering
     * używa rozmiaru proporcjonalnego do faktycznej wysokości wiersza
     * (`GlfwFrameMetrics`), więc ta stała rządzi wyłącznie gęstością siatki.
     */
    private const BASE_FONT_SIZE = 16.0;

    private readonly VGContext $vg;

    private readonly int $cellWidthPixels;

    private readonly int $cellHeightPixels;

    protected function __construct()
    {
        parent::__construct();

        // Okno musi istnieć, zanim powstanie kontekst wektorowy — samo
        // `getInstance()` gwarantuje kolejność niezależnie od drogi wejścia.
        GlfwWindowService::getInstance();

        $this->vg = new VGContext(VGContext::ANTIALIAS | VGContext::STENCIL_STROKES);

        $path = (new GlfwFontLocator())->locate();

        if ($path === null || $this->vg->createFont(self::FONT_NAME, $path) < 0) {
            throw GlfwException::forMissingFont();
        }

        [$this->cellWidthPixels, $this->cellHeightPixels] = $this->measureCell();
    }

    public function context(): VGContext
    {
        return $this->vg;
    }

    /** Szerokość komórki siatki w pikselach — z szerokości znaku fontu. */
    public function cellWidthPixels(): int
    {
        return $this->cellWidthPixels;
    }

    /** Wysokość komórki siatki w pikselach — z proporcji pisma do wiersza. */
    public function cellHeightPixels(): int
    {
        return $this->cellHeightPixels;
    }

    /**
     * Pomiar w parze `beginFrame()`/`cancelFrame()`: mierzenie tekstu wymaga
     * ustawionego stanu kontekstu, a klatki nie ma jeszcze żadnej — anulowana
     * nie zostawia po sobie ani jednego piksela.
     *
     * @return array{int, int}
     */
    private function measureCell(): array
    {
        $this->vg->beginFrame(1.0, 1.0, 1.0);

        try {
            $this->vg->fontFace(self::FONT_NAME);
            $this->vg->fontSize(self::BASE_FONT_SIZE);

            $advance = $this->vg->textBounds(0.0, 0.0, 'M');
        } finally {
            $this->vg->cancelFrame();
        }

        return [
            max(1, (int) ceil($advance)),
            max(1, (int) round(self::BASE_FONT_SIZE / GlfwFrameMetrics::FONT_SIZE_RATIO)),
        ];
    }
}
