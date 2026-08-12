<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Rendering;

use GL\VectorGraphics\VGAlign;
use GL\VectorGraphics\VGColor;
use GL\VectorGraphics\VGContext;
use LightManager\Application\Port\FrameRendererPort;
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
use LightManager\Infrastructure\Glfw\GlfwFrameMetrics;
use LightManager\Infrastructure\Glfw\GlfwViewportService;
use LightManager\Infrastructure\Glfw\GlfwWindowService;
use LightManager\Infrastructure\Glfw\VgContextService;
use LightManager\Infrastructure\Glfw\VgTextureCache;

/**
 * Trzeci tłumacz słownika prymitywów: klatka → wywołania API wektorowego
 * rozszerzenia (NanoVG na GL3) wprost do okna, bez Imagicka w ścieżce klatki
 * (krok 35, D54). Ten sam stos płaszczyzn, te same prymitywy, te same role
 * motywu — a geometria każdego kształtu jest lustrem `SixelFrameEncoder`,
 * łącznie z obwódką biegnącą środkiem skrajnych wierszy i prawą krawędzią
 * liczoną od prawej strony framebuffera.
 *
 * Pierwszy renderer **bez palety indeksowanej**: kolory ról idą na ekran
 * w pełnej głębi, kwantyzacja była ograniczeniem Sixela, nie motywu.
 *
 * Rysowanie jest rozbite na dwa publiczne kroki (`drawFrame()` → `present()`),
 * a `render()` jest ich fasadą — ten sam wzorzec, którym D28 rozcięła potok
 * sixelowy: narzędzie pomiarowe stawia zegar **między** krokami, a w samym
 * rendererze nie ma ani jednego wywołania pomiarowego.
 *
 * Z przełączników jakości kroku 14 obowiązuje tu wyłącznie `strokeAntialias`
 * (→ `shapeAntiAlias` kontekstu). `textAntialias` nie dotyczy — NanoVG
 * wygładza tekst zawsze; `paletteColors` nie dotyczy — palety nie ma (D54).
 */
final class OpenGlFrameRenderer implements FrameRendererPort
{
    /** Długość nogi nawiasu narożnego w kolumnach siatki — jak w `SixelFrameEncoder`. */
    private const BRACKET_COLUMNS = 2;

    private const BRACKET_WIDTH = 2.0;

    private const PREVIEW_CAPTION_GAP_COLUMNS = 2;

    private readonly VgTextureCache $textures;

    private GlfwFrameMetrics $metrics;

    private Theme $theme;

    public function __construct(?VgTextureCache $textures = null)
    {
        $this->textures = $textures ?? new VgTextureCache();
    }

    public function render(Frame $frame): void
    {
        $this->drawFrame($frame, RenderingOptions::current());
        $this->present();
    }

    /**
     * Krok pierwszy: przetłumaczona klatka w tylnym buforze, bez pokazania.
     *
     * Siatkę podaje się z zewnątrz tylko w narzędziu pomiarowym (oś `--grid`);
     * aplikacja zostawia `null` i dostaje tę samą siatkę, z której składał
     * klatkę `FrameComposer` — oba rachunki pytają `GlfwViewportService`.
     */
    public function drawFrame(Frame $frame, RenderingOptions $options, ?int $rows = null, ?int $columns = null): void
    {
        $window = GlfwWindowService::getInstance();
        $size = $window->framebufferSize();
        $viewport = GlfwViewportService::getInstance();

        $this->theme = $options->theme;
        $this->metrics = new GlfwFrameMetrics(
            $size['width'],
            $size['height'],
            $rows ?? $viewport->rows(),
            $columns ?? $viewport->columns(),
        );

        glViewport(0, 0, $size['width'], $size['height']);

        // Rozbiór heksu zamiast `VGColor->r`: stuby rozszerzenia nie deklarują
        // właściwości koloru, a `glClearColor()` i tak chce gołych składowych.
        [$red, $green, $blue] = $this->rgb($this->theme->background);
        glClearColor($red, $green, $blue, 1.0);
        glClear(GL_COLOR_BUFFER_BIT | GL_STENCIL_BUFFER_BIT);

        $vg = VgContextService::getInstance()->context();
        $vg->beginFrame((float) $size['width'], (float) $size['height'], 1.0);
        $vg->shapeAntiAlias($options->strokeAntialias ? 1 : 0);

        foreach ($frame->planes as $plane) {
            $this->drawPlane($vg, $plane);
        }

        $vg->endFrame();
    }

    /** Krok drugi: zamiana buforów — dopiero ona kończy klatkę na ekranie. */
    public function present(): void
    {
        GlfwWindowService::getInstance()->swapBuffers();
    }

    private function drawPlane(VGContext $vg, Plane $plane): void
    {
        if ($plane->opaque) {
            $this->clearPlane($vg, $plane->bounds);
        }

        foreach ($plane->primitives as $primitive) {
            $this->drawPrimitive($vg, $primitive);
        }
    }

    /**
     * Wymazanie prostokąta płaszczyzny nieprzezroczystej kolorem tła motywu —
     * jak w obu pozostałych tłumaczach: warstwa ma zakrywać, nie prześwitywać.
     */
    private function clearPlane(VGContext $vg, Rect $bounds): void
    {
        if ($bounds->isEmpty()) {
            return;
        }

        $left = $this->metrics->xOf($bounds->column);
        $top = $this->metrics->topOf($bounds->row);

        $vg->beginPath();
        $vg->rect(
            (float) $left,
            (float) $top,
            (float) max(1, $this->metrics->rightOf($bounds) - $left + 1),
            (float) max(1, $bounds->rows * $this->metrics->rowHeight),
        );
        $vg->fillColor($this->colorOf(Role::Background));
        $vg->fill();
    }

    private function drawPrimitive(VGContext $vg, Primitive $primitive): void
    {
        match (true) {
            $primitive instanceof TextRun => $this->drawText($vg, $primitive),
            $primitive instanceof RoundRect => $this->drawRoundRect($vg, $primitive),
            $primitive instanceof CornerBrackets => $this->drawBrackets($vg, $primitive),
            $primitive instanceof Bar => $this->drawBar($vg, $primitive),
            $primitive instanceof Scrollbar => $this->drawScrollbar($vg, $primitive),
            $primitive instanceof Bitmap => $this->drawBitmap($vg, $primitive),
            default => null,
        };
    }

    private function drawText(VGContext $vg, TextRun $text): void
    {
        if ($text->text === '') {
            return;
        }

        $x = (float) $this->metrics->xOf($text->column);

        $vg->fontFace(VgContextService::FONT_NAME);
        $vg->fontSize($this->metrics->fontSize);
        $vg->textAlign(VGAlign::LEFT | VGAlign::BASELINE);

        if ($text->clearBehind !== null) {
            $this->drawInlineLabel($vg, $text, $x);

            return;
        }

        $vg->fillColor($this->colorOf($text->role));
        $vg->text($x, (float) $this->metrics->baselineOf($text->row), $text->text);
    }

    /**
     * Etykieta wpięta w linię obwódki: najpierw wycina sobie miejsce w kolorze
     * tła, potem siada **na linii**, nie na linii bazowej wiersza — z literami
     * rozstrzelonymi jak w torze sixelowym.
     */
    private function drawInlineLabel(VGContext $vg, TextRun $text, float $x): void
    {
        $middle = $this->metrics->middleOf($text->row);
        $half = max(2, intdiv((int) $this->metrics->fontSize, 2));
        $spacing = max(1.0, $this->metrics->columnWidth * 0.3);

        $vg->textLetterSpacing($spacing);

        $width = $vg->textBounds($x, (float) $middle, $text->text);

        $vg->beginPath();
        $vg->rect(
            $x - $this->metrics->columnWidth,
            (float) ($middle - $half),
            $width + 2.0 * $this->metrics->columnWidth,
            (float) (2 * $half),
        );
        $vg->fillColor($this->colorOf($text->clearBehind ?? Role::Background));
        $vg->fill();

        $vg->fillColor($this->colorOf($text->role));
        $vg->text($x, (float) ($middle + intdiv($half, 2)), $text->text);

        $vg->textLetterSpacing(0.0);
    }

    /**
     * Prostokąt zaokrąglony: wypełnienie bez obrysu zajmuje pełne komórki,
     * obrys biegnie środkiem skrajnych wierszy — ta sama różnica, którą
     * opisuje prymityw i którą rysuje tor sixelowy.
     */
    private function drawRoundRect(VGContext $vg, RoundRect $rect): void
    {
        $left = $this->metrics->xOf($rect->bounds->column);
        $right = $this->metrics->rightOf($rect->bounds);

        if ($rect->stroke === null) {
            $top = $this->metrics->topOf($rect->bounds->row);
            $bottom = $this->metrics->topOf($rect->bounds->bottom() + 1) - 1;
        } else {
            $top = $this->metrics->middleOf($rect->bounds->row);
            $bottom = $this->metrics->middleOf($rect->bounds->bottom());
        }

        $radius = $this->metrics->radiusFor($rect->corner, $bottom - $top, $right - $left);

        $vg->beginPath();
        $vg->roundedRect(
            (float) $left,
            (float) $top,
            (float) max(1, $right - $left + 1),
            (float) max(1, $bottom - $top + 1),
            (float) $radius,
        );

        if ($rect->fill !== null) {
            $vg->fillColor($this->colorOf($rect->fill));
            $vg->fill();
        }

        if ($rect->stroke !== null) {
            $vg->strokeColor($this->colorOf($rect->stroke));
            $vg->strokeWidth(1.0);
            $vg->stroke();
        }
    }

    /**
     * Nawias narożny: dwie krótkie nogi **wraz z łukiem między nimi**, na
     * lewym górnym i prawym dolnym rogu — łuk należy do nawiasu, nie do
     * obwódki, z powodu opisanego w torze sixelowym (ton obwódki ginie).
     */
    private function drawBrackets(VGContext $vg, CornerBrackets $brackets): void
    {
        $left = (float) $this->metrics->xOf($brackets->bounds->column);
        $right = (float) $this->metrics->rightOf($brackets->bounds);
        $top = (float) $this->metrics->middleOf($brackets->bounds->row);
        $bottom = (float) $this->metrics->middleOf($brackets->bounds->bottom());
        $radius = (float) $this->metrics->radiusFor(
            $brackets->corner,
            (int) ($bottom - $top),
            (int) ($right - $left),
        );
        $leg = (float) (self::BRACKET_COLUMNS * $this->metrics->columnWidth);

        $vg->strokeColor($this->colorOf($brackets->role));
        $vg->strokeWidth(self::BRACKET_WIDTH);
        $vg->lineCap(VGContext::LINECAP_ROUND);

        // Lewy górny: noga w prawo, łuk, noga w dół.
        $vg->beginPath();
        $vg->moveTo($left + $radius + $leg, $top);
        $vg->lineTo($left + $radius, $top);
        $vg->arcTo($left, $top, $left, $top + $radius, $radius);
        $vg->lineTo($left, $top + $radius + $leg);
        $vg->stroke();

        // Prawy dolny: lustrzane odbicie.
        $vg->beginPath();
        $vg->moveTo($right - $radius - $leg, $bottom);
        $vg->lineTo($right - $radius, $bottom);
        $vg->arcTo($right, $bottom, $right, $bottom - $radius, $radius);
        $vg->lineTo($right, $bottom - $radius - $leg);
        $vg->stroke();
    }

    /**
     * Kreska albo płaszczyzna o ostrych narożnikach — włos i krawędź stoją
     * przy lewym boku prostokąta, z oddechem piksela w pionie.
     */
    private function drawBar(VGContext $vg, Bar $bar): void
    {
        $left = $this->metrics->xOf($bar->bounds->column);
        $top = $this->metrics->topOf($bar->bounds->row);
        $height = $this->metrics->topOf($bar->bounds->bottom() + 1) - $top;

        $vg->beginPath();

        if ($bar->weight === Weight::Fill) {
            $vg->rect(
                (float) $left,
                (float) $top,
                (float) max(1, $this->metrics->rightOf($bar->bounds) - $left + 1),
                (float) max(1, $height),
            );
        } else {
            $vg->rect(
                (float) $left,
                (float) ($top + 1),
                $bar->weight === Weight::Edge ? 2.0 : 1.0,
                (float) max(1, $height - 2),
            );
        }

        $vg->fillColor($this->colorOf($bar->role));
        $vg->fill();
    }

    /** Szyna przy prawej krawędzi z suwakiem w akcencie — geometria jak w torze sixelowym. */
    private function drawScrollbar(VGContext $vg, Scrollbar $scrollbar): void
    {
        if (!$scrollbar->position->isNeeded() || $scrollbar->bounds->rows < 1) {
            return;
        }

        $width = max(2, intdiv($this->metrics->columnWidth, 2));
        $right = $this->metrics->rightOf($scrollbar->bounds);
        $left = $right - $width;
        $top = $this->metrics->topOf($scrollbar->bounds->row);
        $bottom = $this->metrics->topOf($scrollbar->bounds->bottom() + 1) - 1;
        $height = max(1, $bottom - $top);

        $vg->beginPath();
        $vg->roundedRect((float) $left, (float) $top, (float) $width, (float) $height, (float) $width);
        $vg->fillColor($this->colorOf($scrollbar->rail));
        $vg->fill();

        $thumbHeight = max(
            $this->metrics->rowHeight,
            (int) round($height * $scrollbar->position->visibleFraction()),
        );
        $thumbTop = $top + (int) round(($height - $thumbHeight) * $scrollbar->position->progress());

        $vg->beginPath();
        $vg->roundedRect((float) $left, (float) $thumbTop, (float) $width, (float) $thumbHeight, (float) $width);
        $vg->fillColor($this->colorOf($scrollbar->thumb));
        $vg->fill();
    }

    /**
     * Miniatura z podpisem obok; gdy obrazu nie ma albo nie da się go
     * zdekodować — ramka z powodem w środku, jak w torze sixelowym.
     *
     * Piksele przychodzą z tekstury (`VgTextureCache`): dekodowanie płaci się
     * raz na plik, nie raz na klatkę. Skalowanie do prostokąta robi GPU przy
     * rysowaniu — tekstura niesie oryginalne wymiary.
     */
    private function drawBitmap(VGContext $vg, Bitmap $image): void
    {
        $left = $this->metrics->xOf($image->bounds->column);
        $top = $this->metrics->topOf($image->bounds->row);
        $maxWidth = max(1, $image->bounds->columns * $this->metrics->columnWidth);
        $maxHeight = max(1, $image->bounds->rows * $this->metrics->rowHeight);

        $entry = $image->path === null ? null : $this->textures->textureFor($image->path);

        if ($entry === null) {
            $this->drawEmptyBox($vg, $image->caption, $left, $top, $maxWidth, $maxHeight);

            return;
        }

        // Dopasowanie w prostokąt z zachowaniem proporcji — jak `bestfit`
        // w `ThumbnailService`.
        $scale = min($maxWidth / $entry['width'], $maxHeight / $entry['height']);
        $drawWidth = max(1.0, $entry['width'] * $scale);
        $drawHeight = max(1.0, $entry['height'] * $scale);

        $vg->beginPath();
        $vg->rect((float) $left, (float) $top, $drawWidth, $drawHeight);
        $vg->fillPaint($entry['image']->makePaint((float) $left, (float) $top, $drawWidth, $drawHeight));
        $vg->fill();

        $vg->fontFace(VgContextService::FONT_NAME);
        $vg->fontSize($this->metrics->fontSize);
        $vg->textAlign(VGAlign::LEFT | VGAlign::BASELINE);
        $vg->fillColor($this->colorOf(Role::Muted));
        $vg->text(
            (float) ($left + (int) $drawWidth + self::PREVIEW_CAPTION_GAP_COLUMNS * $this->metrics->columnWidth),
            (float) $this->metrics->baselineOf($image->bounds->row),
            $image->caption,
        );
    }

    private function drawEmptyBox(
        VGContext $vg,
        string $caption,
        int $left,
        int $top,
        int $width,
        int $height,
    ): void {
        $vg->beginPath();
        $vg->roundedRect(
            (float) $left,
            (float) $top,
            (float) $width,
            (float) $height,
            (float) $this->metrics->cornerRadius,
        );
        $vg->strokeColor($this->colorOf(Role::Border));
        $vg->strokeWidth(1.0);
        $vg->stroke();

        $vg->fontFace(VgContextService::FONT_NAME);
        $vg->fontSize($this->metrics->fontSize);
        $vg->textAlign(VGAlign::CENTER | VGAlign::MIDDLE);
        $vg->fillColor($this->colorOf(Role::Muted));
        $vg->text((float) ($left + intdiv($width, 2)), (float) ($top + intdiv($height, 2)), $caption);
        $vg->textAlign(VGAlign::LEFT | VGAlign::BASELINE);
    }

    /**
     * Kolor roli motywu jako składowe 0–1 dla `glClearColor()`.
     *
     * @return array{float, float, float}
     */
    private function rgb(string $hex): array
    {
        $value = (int) hexdec(ltrim($hex, '#'));

        return [
            ($value >> 16 & 0xFF) / 255.0,
            ($value >> 8 & 0xFF) / 255.0,
            ($value & 0xFF) / 255.0,
        ];
    }

    private function colorOf(Role $role): VGColor
    {
        return VGColor::hex(match ($role) {
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
        });
    }
}
