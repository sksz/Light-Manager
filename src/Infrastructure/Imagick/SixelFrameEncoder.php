<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Imagick;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use LightManager\Application\Ui\Corner;
use LightManager\Application\Ui\Frame;
use LightManager\Application\Ui\Plane;
use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Bitmap;
use LightManager\Application\Ui\Primitive\CornerBrackets;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Primitive\Weight;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Infrastructure\Rendering\RenderingOptions;
use LightManager\Infrastructure\Rendering\Theme;

/**
 * Zamienia klatkę na bajty w formacie Sixel.
 *
 * Nie wie nic o terminalu **ani o tym, co przedstawia klatka**. Dostaje stos
 * płaszczyzn z prymitywami i rozmiar płótna, oddaje gotowy blob. Do kroku 18
 * znał znaczenie każdego pola klatki z osobna — listę wpisów, pasek stanu, pas
 * podglądu, okienko — i każdy nowy element interfejsu wymagał tu nowej metody
 * rysującej. Dziś zna pięć kształtów i tyle mu wystarcza.
 *
 * **Prostokąt w siatce znakowej mapuje się na piksele lustrzanie**: lewa
 * krawędź liczy się od lewej strony płótna, prawa — od prawej. Wygląda to na
 * drobiazg, a jest jedynym sposobem, by pełnej szerokości kształt miał
 * jednakowe marginesy po obu stronach na płótnie, którego szerokość nie dzieli
 * się przez liczbę kolumn (1000 pikseli i 166 kolumn to komórka 6-pikselowa
 * i cztery piksele reszty).
 *
 * Kolejność kroków przed enkodowaniem jest wynikiem pomiarów z kroku 08:
 * kwantyzacja i przełączenie obrazu na typ paletowy skracają enkodowanie
 * z ~425 ms do ~120 ms przy płótnie 800×600.
 *
 * Potok jest rozbity na trzy jawne kroki (`drawCanvas()` → `quantizeCanvas()` →
 * `toSixel()`), a `encode()` jest tylko ich fasadą. Rozbicie powstało w kroku 16
 * po to, by narzędzie pomiarowe mogło zmierzyć każdą fazę **z zewnątrz**
 * ([00-decyzje.md](../../../docs/plans/00-decyzje.md), D28).
 *
 * Właścicielem płótna jest ten, kto wywołał `drawCanvas()`.
 */
final class SixelFrameEncoder
{
    /**
     * Sufit palety dla klatki z miniaturą — pełne 256 wpisów Sixela. Palety
     * tekstowej starczy na obwódki i półcienie liter, ale ze zdjęcia zrobiłaby
     * plakat, więc podgląd dostaje więcej; płacimy tylko wtedy, gdy w klatce
     * naprawdę leży bitmapa.
     *
     * Wpisy motywu biorą z tego sufitu swoje (`paletteColors` z konfiguracji),
     * reszta idzie na barwy zdjęcia — podział liczy `ThemePalette`.
     */
    private const PALETTE_COLORS_WITH_IMAGE = 256;

    /** Długość nogi nawiasu narożnego, w kolumnach siatki (bez łuku). */
    private const BRACKET_COLUMNS = 2;

    /** Nawias jest grubszy od obwódki — to on ma nieść kształt narożnika. */
    private const BRACKET_WIDTH = 2;

    private const PREVIEW_CAPTION_GAP_COLUMNS = 2;

    private const TEXT_WIDTH_CACHE_LIMIT = 64;

    /** @var array<string, float> zmierzone szerokości napisów, klucz: treść i rozmiar pisma */
    private array $textWidths = [];

    /** Palety Sixela zbudowane z kolorów motywu — wejście do `remapImage()`. */
    private readonly ThemePalette $palettes;

    /** Zrasteryzowane napisy i płaszczyzny zaznaczenia, żeby nie powstawały w kółko. */
    private readonly RowBitmapCache $bitmaps;

    /**
     * Zapamiętana płaszczyzna spodnia wraz z tłem.
     *
     * Jedna, nie mapa: oprawa zmienia się przy zmianie okna albo motywu, a wtedy
     * poprzednia i tak nie ma po co żyć. Do kroku 18 zapamiętany był „chrom”,
     * czyli cztery panele wypisane w enkoderze z nazwy; dziś jest to po prostu
     * pierwsza płaszczyzna klatki i enkoder nie wie, co na niej leży.
     */
    private ?Imagick $base = null;

    private string $baseKey = '';

    private RenderingOptions $options;

    private Theme $theme;

    private SixelFrameMetrics $metrics;

    private bool $canvasCarriesBitmap = false;

    /** @var list<string> barwy miniatury leżącej w klatce — dopisek do palety motywu */
    private array $bitmapColors = [];

    public function __construct()
    {
        $this->palettes = new ThemePalette();
        $this->bitmaps = new RowBitmapCache();
    }

    /**
     * Cały potok w jednym wywołaniu — droga, którą chodzi aplikacja.
     *
     * Płótno jest zwalniane w `finally`, więc wyjątek z kwantyzacji albo
     * z enkodowania nie zostawia po sobie zajętej pamięci ImageMagicka.
     */
    public function encode(
        Frame $frame,
        RenderingOptions $options,
        int $widthPixels,
        int $heightPixels,
        int $rows,
        int $columns,
    ): string {
        $canvas = $this->drawCanvas($frame, $options, $widthPixels, $heightPixels, $rows, $columns);

        try {
            $this->quantizeCanvas($canvas, $this->canvasCarriesBitmap);

            return $this->toSixel($canvas);
        } finally {
            $canvas->clear();
        }
    }

    /**
     * Krok pierwszy: gotowe płótno RGB, przed kwantyzacją.
     *
     * **Zwolnienie płótna należy do wołającego.** Poza `encode()` woła ją
     * narzędzie pomiarowe (zrzut do PNG bierze płótno właśnie stąd, bo po
     * kwantyzacji obrazek pokazywałby skutki palety, a nie samego rysowania).
     */
    public function drawCanvas(
        Frame $frame,
        RenderingOptions $options,
        int $widthPixels,
        int $heightPixels,
        int $rows,
        int $columns,
    ): Imagick {
        $this->options = $options;
        $this->theme = $options->theme;
        $this->metrics = new SixelFrameMetrics($widthPixels, $heightPixels, max(1, $rows), max(1, $columns));
        $this->canvasCarriesBitmap = false;
        $this->bitmapColors = [];

        $planes = $frame->planes;
        $canvas = $this->baseCanvas($planes[0] ?? null);

        foreach (array_slice($planes, 1) as $plane) {
            $this->drawPlane($canvas, $plane);
        }

        return $canvas;
    }

    /**
     * Decyzja podjęta przy ostatnim rysowaniu: czy w klatce leży bitmapa.
     *
     * Narzędzie pomiarowe pyta o nią zamiast zgadywać, żeby mierzony potok
     * wybierał paletę dokładnie tak samo jak aplikacja.
     */
    public function canvasCarriesBitmap(): bool
    {
        return $this->canvasCarriesBitmap;
    }

    /**
     * Krok drugi: sprowadzenie klatki do palety i przełączenie obrazu na typ
     * paletowy.
     *
     * Zawsze `remapImage()` na palecie zbudowanej z góry — kolory, które sami
     * wybraliśmy, trafiają na ekran nietknięte, a kosztuje to ~10 ms zamiast
     * ~47 ms (szczegóły: `ThemePalette`). Klatka z miniaturą różni się tylko
     * tym, że paleta niesie dodatkowo barwy zdjęcia.
     *
     * Do tego miejsca podgląd szedł kwantyzacją adaptacyjną całego płótna
     * (krok 12, D24) i to ona odpowiadała za przemalowanie interfejsu w chwili
     * najechania na plik graficzny: paleta liczona z zawartości zdjęcia
     * przyciągała do siebie kolory motywu. Zmierzone na Grafcie — akcent
     * `#d9a441` → `#b15f0d`, tło `#16181c` → `#020203`. To ta sama pułapka,
     * którą dla klatek bez bitmapy rozbroiła D27; tu została przeoczona.
     */
    public function quantizeCanvas(Imagick $canvas, bool $carriesBitmap): void
    {
        $canvas->remapImage(
            $carriesBitmap
                ? $this->palettes->forThemeWithImage($this->theme, $this->options->paletteColors, $this->bitmapColors)
                : $this->palettes->forTheme($this->theme, $this->options->paletteColors),
            Imagick::DITHERMETHOD_NO,
        );

        $canvas->setImageType(Imagick::IMGTYPE_PALETTE);
    }

    /** Krok trzeci: bajty formatu Sixel. Płótna nie zwalnia — należy do wołającego. */
    public function toSixel(Imagick $canvas): string
    {
        $canvas->setImageFormat('sixel');

        return $canvas->getImageBlob();
    }

    /**
     * Świeże płótno z narysowaną płaszczyzną spodnią — kopia zapamiętanej.
     *
     * Płaszczyzna spodnia niesie oprawę: panele, łuki, nawiasy narożne i
     * etykiety stref. Zależy wyłącznie od rozmiaru okna, podziału na strefy
     * i motywu, więc przy nieruchomym oknie powstaje raz na całe uruchomienie.
     * Gdy jej podpis się zmieni — bo okno urosło albo użytkownik przełączył
     * motyw — pamięć odświeża się sama, bez ścieżki unieważnienia, o której
     * można zapomnieć.
     */
    private function baseCanvas(?Plane $base): Imagick
    {
        $key = ($base?->signature() ?? '') . "\x1e" . $this->metricsKey();

        if ($this->base === null || $this->baseKey !== $key) {
            $this->base?->clear();

            $canvas = new Imagick();
            $canvas->newImage(
                $this->metrics->widthPixels,
                $this->metrics->heightPixels,
                new ImagickPixel($this->theme->background),
            );

            if ($base !== null) {
                $this->drawPlane($canvas, $base);
            }

            $this->base = $canvas;
            $this->baseKey = $key;
        }

        return clone $this->base;
    }

    /** Wszystko poza treścią, co wpływa na piksele — wspólny ogon każdego klucza. */
    private function metricsKey(): string
    {
        return $this->metrics->widthPixels . 'x' . $this->metrics->heightPixels
            . "\x1e" . $this->metrics->rowHeight . 'x' . $this->metrics->columnWidth
            . "\x1e" . $this->metrics->fontSize
            . "\x1e" . $this->metrics->columns
            . "\x1e" . $this->theme->background . $this->theme->border . $this->theme->accent
            . $this->theme->text . $this->theme->muted . $this->theme->selection . $this->theme->selectionText
            . "\x1e" . ($this->options->strokeAntialias ? '1' : '0')
            . "\x1e" . ($this->options->textAntialias ? '1' : '0')
            . "\x1e" . ($this->options->font ?? '');
    }

    private function drawPlane(Imagick $canvas, Plane $plane): void
    {
        if ($plane->opaque) {
            $this->clearPlane($canvas, $plane->bounds);
        }

        foreach ($plane->primitives as $primitive) {
            $this->drawPrimitive($canvas, $primitive);
        }
    }

    /**
     * Wymazanie prostokąta płaszczyzny nieprzezroczystej.
     *
     * Prostokąt jest malowany **kolorem tła motywu**, a nie zasłoną z
     * przezroczystością — i to nie jest szczegół. Klatka bez bitmapy zawiera
     * wyłącznie kolory motywu, a na tym stoi szybka ścieżka palety z kroku 17
     * (D34); zasłona wpuściłaby do niej barwy pośrednie i kwantyzacja skoczyłaby
     * z 9 do 84 ms, tak jak przy wycofanym przygaszaniu (krok 18).
     */
    private function clearPlane(Imagick $canvas, Rect $bounds): void
    {
        if ($bounds->isEmpty()) {
            return;
        }

        $left = $this->metrics->xOf($bounds->column);
        $top = $this->metrics->topOf($bounds->row);
        $width = max(1, $this->rightOf($bounds) - $left + 1);
        $height = max(1, $bounds->rows * $this->metrics->rowHeight);

        // Prostokąt idzie przez pamięć podręczną bitmap i `compositeImage`, a nie
        // przez `drawImage()` — ten ostatni kosztuje tyle, ile **całe płótno**,
        // niezależnie od wielkości kształtu (krok 17, dźwignia 5; ta sama pułapka
        // złapała krawędź zaznaczenia w kroku 18: +17 ms na klatkę).
        $key = "\x1dC" . $width . 'x' . $height . "\x1e" . $this->theme->background;
        $bitmap = $this->bitmaps->get($key);

        if ($bitmap === null) {
            $bitmap = new Imagick();
            $bitmap->newImage($width, $height, new ImagickPixel($this->theme->background));

            $this->bitmaps->put($key, $bitmap);
        }

        $canvas->compositeImage($bitmap, Imagick::COMPOSITE_OVER, $left, $top);
    }

    private function drawPrimitive(Imagick $canvas, Primitive $primitive): void
    {
        match (true) {
            $primitive instanceof TextRun => $this->drawText($canvas, $primitive),
            $primitive instanceof RoundRect => $this->drawRoundRect($canvas, $primitive),
            $primitive instanceof CornerBrackets => $this->drawBrackets($canvas, $primitive),
            $primitive instanceof Bar => $this->drawBar($canvas, $primitive),
            $primitive instanceof Scrollbar => $this->drawScrollbar($canvas, $primitive),
            $primitive instanceof Bitmap => $this->drawBitmap($canvas, $primitive),
            default => null,
        };
    }

    /**
     * Napis składany z zapamiętanej bitmapy — pętla przerysowuje tę samą treść
     * trzydzieści razy na sekundę (krok 17, dźwignia 3).
     *
     * Napis „wpięty w linię” (`clearBehind`) jest wyjątkiem pod dwoma względami:
     * najpierw wycina sobie miejsce w kolorze tła, a potem siada **na linii
     * obwódki**, a nie na linii bazowej wiersza. Bez wycięcia kreska panelu
     * przechodziłaby przez litery etykiety.
     */
    private function drawText(Imagick $canvas, TextRun $text): void
    {
        if ($text->text === '') {
            return;
        }

        $x = $this->metrics->xOf($text->column);

        if ($text->clearBehind !== null) {
            $this->drawInlineLabel($canvas, $text, $x);

            return;
        }

        $canvas->compositeImage(
            $this->textBitmap($text),
            Imagick::COMPOSITE_OVER,
            $x,
            $this->metrics->topOf($text->row),
        );
    }

    private function drawInlineLabel(Imagick $canvas, TextRun $text, int $x): void
    {
        $pen = $this->pen($this->colorOf($text->role));
        $pen->setTextKerning(max(1.0, $this->metrics->columnWidth * 0.3));

        $middle = $this->metrics->middleOf($text->row);
        $half = max(2, intdiv($this->metrics->fontSize, 2));
        $width = (int) ceil($this->textWidth($pen, $text->text));

        $hole = new ImagickDraw();
        $hole->setFillColor(new ImagickPixel($this->colorOf($text->clearBehind ?? Role::Background)));
        $hole->rectangle(
            $x - $this->metrics->columnWidth,
            $middle - $half,
            $x + $width + $this->metrics->columnWidth,
            $middle + $half,
        );
        $canvas->drawImage($hole);

        $canvas->annotateImage($pen, $x, $middle + intdiv($half, 2), 0, $text->text);
    }

    /**
     * Bitmapa napisu — przezroczysta poza literami, więc płaszczyzna zaznaczenia
     * narysowana wcześniej prześwituje spod nich, a ogonki schodzące poniżej
     * wiersza nachodzą na następny tak samo, jak nachodziły przy rysowaniu
     * wprost na płótnie.
     */
    private function textBitmap(TextRun $text): Imagick
    {
        $color = $this->colorOf($text->role);
        $key = "\x1dT" . $text->text . "\x1e" . $color . "\x1e" . $this->metricsKey();
        $cached = $this->bitmaps->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $bitmap = new Imagick();
        $bitmap->newImage(
            max(1, (mb_strlen($text->text) + 1) * $this->metrics->columnWidth),
            $this->metrics->rowBitmapHeight(),
            new ImagickPixel('none'),
        );

        $bitmap->annotateImage($this->pen($color), 0, $this->metrics->baselineWithinRow(), 0, $text->text);

        $this->bitmaps->put($key, $bitmap);

        return $bitmap;
    }

    /**
     * Prostokąt zaokrąglony. Wypełnienie zajmuje pełne komórki, obrys biegnie
     * środkiem skrajnych wierszy — różnica opisana przy samym prymitywie.
     */
    private function drawRoundRect(Imagick $canvas, RoundRect $rect): void
    {
        if ($rect->fill !== null && $rect->stroke === null) {
            $this->drawFilledRect($canvas, $rect);

            return;
        }

        $left = $this->metrics->xOf($rect->bounds->column);
        $right = $this->rightOf($rect->bounds);
        $top = $rect->stroke === null
            ? $this->metrics->topOf($rect->bounds->row)
            : $this->metrics->middleOf($rect->bounds->row);
        $bottom = $rect->stroke === null
            ? $this->metrics->topOf($rect->bounds->bottom() + 1) - 1
            : $this->metrics->middleOf($rect->bounds->bottom());

        $box = new ImagickDraw();
        $box->setFillColor(new ImagickPixel($rect->fill === null ? 'none' : $this->colorOf($rect->fill)));
        $box->setStrokeColor(new ImagickPixel($rect->stroke === null ? 'none' : $this->colorOf($rect->stroke)));
        $box->setStrokeWidth(1);
        $box->setStrokeAntialias($this->options->strokeAntialias);

        $radius = $this->radiusFor($rect->corner, $bottom - $top, $right - $left);
        $box->roundRectangle($left, $top, $right, $bottom, $radius, $radius);

        $canvas->drawImage($box);
    }

    /**
     * Wypełniony prostokąt bez obrysu — czyli płaszczyzna zaznaczenia.
     *
     * Zapamiętywana jako bitmapa, bo jej kształt jest **identyczny w każdym
     * wierszu** i zmienia się wyłącznie położenie w pionie. Rysowana wprost na
     * płótnie była najdroższym pojedynczym elementem klatki, co pokazał dopiero
     * pomiar z kroku 16 (dźwignia 5 kroku 17).
     */
    private function drawFilledRect(Imagick $canvas, RoundRect $rect): void
    {
        $left = $this->metrics->xOf($rect->bounds->column);
        $right = $this->rightOf($rect->bounds);
        $top = $this->metrics->topOf($rect->bounds->row);
        $height = $rect->bounds->rows * $this->metrics->rowHeight;
        $width = max(1, $right - $left + 1);

        $key = "\x1dF" . $width . 'x' . $height
            . "\x1e" . $this->colorOf($rect->fill ?? Role::Selection)
            . "\x1e" . $rect->corner->name
            . "\x1e" . ($this->options->strokeAntialias ? '1' : '0');

        $bitmap = $this->bitmaps->get($key);

        if ($bitmap === null) {
            $bitmap = new Imagick();
            $bitmap->newImage($width, $height, new ImagickPixel('none'));

            $shape = new ImagickDraw();
            $shape->setFillColor(new ImagickPixel($this->colorOf($rect->fill ?? Role::Selection)));
            $shape->setStrokeColor(new ImagickPixel('none'));
            $shape->setStrokeAntialias($this->options->strokeAntialias);

            $radius = $this->radiusFor($rect->corner, $height - 1, $width - 1);
            $shape->roundRectangle(0, 0, $width - 1, $height - 1, $radius, $radius);
            $bitmap->drawImage($shape);

            $this->bitmaps->put($key, $bitmap);
        }

        $canvas->compositeImage($bitmap, Imagick::COMPOSITE_OVER, $left, $top);
    }

    /**
     * Nawias narożny: dwie krótkie nogi **wraz z łukiem między nimi**, na dwóch
     * przeciwległych rogach.
     *
     * Łuk musi należeć do nawiasu, a nie zostać obwódce. Wersja, w której nogi
     * zaczynały się dopiero za promieniem, dawała na ekranie kreskę poziomą,
     * dziurę i kreskę pionową — zaokrąglenie było wprawdzie narysowane, ale
     * kolorem obwódki, czyli tonem, którego w terminalu praktycznie nie widać.
     */
    private function drawBrackets(Imagick $canvas, CornerBrackets $brackets): void
    {
        $left = $this->metrics->xOf($brackets->bounds->column);
        $right = $this->rightOf($brackets->bounds);
        $top = $this->metrics->middleOf($brackets->bounds->row);
        $bottom = $this->metrics->middleOf($brackets->bounds->bottom());
        $radius = $this->radiusFor($brackets->corner, $bottom - $top, $right - $left);
        $leg = self::BRACKET_COLUMNS * $this->metrics->columnWidth;

        $bracket = new ImagickDraw();
        $bracket->setStrokeColor(new ImagickPixel($this->colorOf($brackets->role)));
        $bracket->setStrokeWidth(self::BRACKET_WIDTH);
        $bracket->setStrokeAntialias($this->options->strokeAntialias);
        $bracket->setStrokeLineCap(Imagick::LINECAP_ROUND);
        $bracket->setFillColor(new ImagickPixel('none'));

        // Lewy górny: noga w prawo, łuk, noga w dół.
        $bracket->pathStart();
        $bracket->pathMoveToAbsolute($left + $radius + $leg, $top);
        $bracket->pathLineToAbsolute($left + $radius, $top);
        $bracket->pathEllipticArcAbsolute($radius, $radius, 0, false, false, $left, $top + $radius);
        $bracket->pathLineToAbsolute($left, $top + $radius + $leg);
        $bracket->pathFinish();

        // Prawy dolny: lustrzane odbicie.
        $bracket->pathStart();
        $bracket->pathMoveToAbsolute($right - $radius - $leg, $bottom);
        $bracket->pathLineToAbsolute($right - $radius, $bottom);
        $bracket->pathEllipticArcAbsolute($radius, $radius, 0, false, false, $right, $bottom - $radius);
        $bracket->pathLineToAbsolute($right, $bottom - $radius - $leg);
        $bracket->pathFinish();

        $canvas->drawImage($bracket);
    }

    /**
     * Kreska albo płaszczyzna o ostrych narożnikach. Włos i krawędź stoją przy
     * lewym boku prostokąta i mają oddech w pionie — inaczej pionowa przegroda
     * w pasku stanu dotykałaby obwódki panelu.
     */
    private function drawBar(Imagick $canvas, Bar $bar): void
    {
        $left = $this->metrics->xOf($bar->bounds->column);
        $top = $this->metrics->topOf($bar->bounds->row);
        $bottom = $this->metrics->topOf($bar->bounds->bottom() + 1) - 1;

        if ($bar->weight !== Weight::Fill) {
            $this->drawThinBar($canvas, $bar, $left, $top, $bottom - $top + 1);

            return;
        }

        $shape = new ImagickDraw();
        $shape->setFillColor(new ImagickPixel($this->colorOf($bar->role)));
        $shape->setStrokeColor(new ImagickPixel('none'));
        $shape->rectangle($left, $top, $this->rightOf($bar->bounds), $bottom);

        $canvas->drawImage($shape);
    }

    /**
     * Włos i krawędź składane z zapamiętanej bitmapy, a nie rysowane wprost.
     *
     * Wygląda to na przesadę wobec kreski szerokiej na dwa piksele, ale koszt
     * `drawImage()` nie zależy od wielkości kształtu, tylko od wielkości płótna:
     * krawędź zaznaczenia rysowana wprost w każdym z czterdziestu sześciu
     * wierszy kosztowała **17 ms na klatkę** — więcej niż wszystkie napisy
     * razem. To ta sama lekcja, co przy pasku zaznaczenia w kroku 17 (dźwignia
     * 5), tyle że zapomniana przy okazji rozbicia paska na dwa prymitywy.
     */
    private function drawThinBar(Imagick $canvas, Bar $bar, int $left, int $top, int $height): void
    {
        $width = $bar->weight === Weight::Edge ? 2 : 1;
        $color = $this->colorOf($bar->role);
        $key = "\x1dB" . $width . 'x' . $height . "\x1e" . $color;
        $bitmap = $this->bitmaps->get($key);

        if ($bitmap === null) {
            $bitmap = new Imagick();
            $bitmap->newImage($width, max(1, $height), new ImagickPixel('none'));

            $shape = new ImagickDraw();
            $shape->setFillColor(new ImagickPixel($color));
            $shape->setStrokeColor(new ImagickPixel('none'));
            $shape->rectangle(0, 1, $width - 1, max(1, $height) - 2);
            $bitmap->drawImage($shape);

            $this->bitmaps->put($key, $bitmap);
        }

        $canvas->compositeImage($bitmap, Imagick::COMPOSITE_OVER, $left, $top);
    }

    /**
     * Szyna przy prawej krawędzi panelu z suwakiem w akcencie. Bez niej długi
     * katalog nie mówi nic o tym, gdzie w nim jesteśmy.
     */
    private function drawScrollbar(Imagick $canvas, Scrollbar $scrollbar): void
    {
        if (!$scrollbar->position->isNeeded() || $scrollbar->bounds->rows < 1) {
            return;
        }

        $width = max(2, intdiv($this->metrics->columnWidth, 2));
        $right = $this->rightOf($scrollbar->bounds);
        $left = $right - $width;
        $top = $this->metrics->topOf($scrollbar->bounds->row);
        $bottom = $this->metrics->topOf($scrollbar->bounds->bottom() + 1) - 1;
        $height = max(1, $bottom - $top);

        $rail = new ImagickDraw();
        $rail->setFillColor(new ImagickPixel($this->colorOf($scrollbar->rail)));
        $rail->roundRectangle($left, $top, $right, $bottom, $width, $width);
        $canvas->drawImage($rail);

        $thumbHeight = max(
            $this->metrics->rowHeight,
            (int) round($height * $scrollbar->position->visibleFraction()),
        );
        $thumbTop = $top + (int) round(($height - $thumbHeight) * $scrollbar->position->progress());

        $thumb = new ImagickDraw();
        $thumb->setFillColor(new ImagickPixel($this->colorOf($scrollbar->thumb)));
        $thumb->roundRectangle($left, $thumbTop, $right, $thumbTop + $thumbHeight, $width, $width);
        $canvas->drawImage($thumb);
    }

    /**
     * Miniatura i podpis obok niej; gdy obrazu nie ma — ramka z powodem
     * w środku, bo pusty prostokąt wyglądałby jak błąd rysowania.
     *
     * To jedyne miejsce, w którym enkoder sięga po plik z dysku, i jedyne, które
     * przestawia znacznik `canvasCarriesBitmap` wraz z barwami zdjęcia — od nich
     * zależy paleta całej klatki.
     */
    private function drawBitmap(Imagick $canvas, Bitmap $image): void
    {
        $left = $this->metrics->xOf($image->bounds->column);
        $top = $this->metrics->topOf($image->bounds->row);
        $width = max(1, $image->bounds->columns * $this->metrics->columnWidth);
        $height = max(1, $image->bounds->rows * $this->metrics->rowHeight);

        $thumbnail = $image->path === null
            ? null
            : ThumbnailService::getInstance()->thumbnail(
                $image->path,
                $width,
                $height,
                $this->theme->background,
                $this->palettes->roomForImage(
                    $this->theme,
                    $this->options->paletteColors,
                    self::PALETTE_COLORS_WITH_IMAGE,
                ),
            );

        if ($thumbnail === null) {
            $this->drawEmptyBox($canvas, $image->caption, $left, $top, $width, $height);

            return;
        }

        $canvas->compositeImage($thumbnail->image, Imagick::COMPOSITE_OVER, $left, $top);
        $this->canvasCarriesBitmap = true;
        $this->bitmapColors = $thumbnail->colors;

        $canvas->annotateImage(
            $this->pen($this->theme->muted),
            $left + $thumbnail->image->getImageWidth()
                + self::PREVIEW_CAPTION_GAP_COLUMNS * $this->metrics->columnWidth,
            $this->metrics->baselineOf($image->bounds->row),
            0,
            $image->caption,
        );
    }

    private function drawEmptyBox(
        Imagick $canvas,
        string $caption,
        int $left,
        int $top,
        int $width,
        int $height,
    ): void {
        $box = new ImagickDraw();
        $box->setFillColor(new ImagickPixel('none'));
        $box->setStrokeColor(new ImagickPixel($this->theme->border));
        $box->setStrokeWidth(1);
        $box->setStrokeAntialias($this->options->strokeAntialias);
        $box->roundRectangle(
            $left,
            $top,
            $left + $width,
            $top + $height,
            $this->metrics->cornerRadius,
            $this->metrics->cornerRadius,
        );
        $canvas->drawImage($box);

        $pen = $this->pen($this->theme->muted);
        $pen->setTextAlignment(Imagick::ALIGN_CENTER);

        $canvas->annotateImage($pen, $left + intdiv($width, 2), $top + intdiv($height, 2), 0, $caption);
    }

    /**
     * Prawa krawędź prostokąta w pikselach — **liczona od prawej krawędzi
     * płótna**, lustrzanie wobec lewej.
     *
     * Dzięki temu kształt rozpięty na całą szerokość ma po obu stronach
     * jednakowy margines, także wtedy, gdy szerokość płótna nie dzieli się przez
     * liczbę kolumn. Liczona po siatce byłaby o resztę z dzielenia bliżej środka
     * po prawej stronie niż po lewej — a przy komórce 6-pikselowej to cztery
     * piksele różnicy, widoczne na obwódce panelu.
     */
    private function rightOf(Rect $bounds): int
    {
        return $this->metrics->widthPixels
            - $this->metrics->xOf(max(0, $this->metrics->columns - 1 - $bounds->right()))
            - 1;
    }

    /**
     * Promień zaokrąglenia nie może przekroczyć połowy boku, inaczej łuki
     * jednego rogu wchodzą w łuki drugiego — a panel stanu bywa wysoki na dwa
     * wiersze.
     */
    private function radiusFor(Corner $corner, int $height, int $width): int
    {
        $wanted = match ($corner) {
            Corner::Round => $this->metrics->cornerRadius,
            Corner::Soft => max(2, intdiv($this->metrics->rowHeight, 3)),
        };

        return max(1, min($wanted, intdiv($height, 2), intdiv($width, 2)));
    }

    private function colorOf(Role $role): string
    {
        return match ($role) {
            Role::Background => $this->theme->background,
            Role::Surface => $this->theme->surface,
            Role::Border => $this->theme->border,
            Role::Text => $this->theme->text,
            Role::Muted => $this->theme->muted,
            Role::Accent => $this->theme->accent,
            Role::Selection => $this->theme->selection,
            Role::SelectionText => $this->theme->selectionText,
            Role::Info => $this->theme->info,
            Role::Warning => $this->theme->warning,
            Role::Danger => $this->theme->danger,
        };
    }

    /**
     * Szerokość napisu mierzona przez Imagicka i zapamiętana: etykiety stref są
     * stałe, a pętla składa klatkę trzydzieści razy na sekundę.
     */
    private function textWidth(ImagickDraw $pen, string $text): float
    {
        $key = $text . '|' . $this->metrics->fontSize;

        if (isset($this->textWidths[$key])) {
            return $this->textWidths[$key];
        }

        if (count($this->textWidths) >= self::TEXT_WIDTH_CACHE_LIMIT) {
            $this->textWidths = [];
        }

        $probe = new Imagick();
        $probe->newImage(1, 1, new ImagickPixel('none'));

        /** @var array{textWidth: float} $font */
        $font = $probe->queryFontMetrics($pen, $text);
        $probe->clear();

        return $this->textWidths[$key] = $font['textWidth'];
    }

    private function pen(string $color): ImagickDraw
    {
        $pen = new ImagickDraw();
        $pen->setFontSize($this->metrics->fontSize);
        $pen->setFillColor(new ImagickPixel($color));
        $pen->setTextAntialias($this->options->textAntialias);

        // Aplikacja zawsze zdaje się na listę preferencji; nazwa wpisana wprost
        // przychodzi wyłącznie z narzędzia pomiarowego, dla którego font jest
        // osią konfiguracji (krok 16).
        $font = $this->options->font ?? ImagickCapabilityService::getInstance()->monospaceFont();

        if ($font !== null) {
            $pen->setFont($font);
        }

        return $pen;
    }
}
