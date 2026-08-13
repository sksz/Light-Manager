<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Rendering;

use LightManager\Application\Port\FrameRendererPort;
use LightManager\Application\Ui\Frame;
use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Bitmap;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\TextMark;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Primitive\Weight;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Infrastructure\Terminal\TerminalService;
use LightManager\Infrastructure\Terminal\TerminalSizeService;

/**
 * Rysuje klatkę zwykłym tekstem z kodami ANSI — używany, gdy terminal albo
 * ImageMagick nie obsługują Sixela.
 *
 * Te same płaszczyzny i te same prymitywy co w trybie graficznym; różnica jest
 * w **degradacji kształtów**, nie w treści. Obwódka staje się znakami
 * rysunkowymi, nawias narożny i suwak znikają (pół kolumny w siatce znakowej nie
 * istnieje), a miniatura zostaje samym podpisem. Do kroku 18 tryb tekstowy miał
 * własną, niezależną ścieżkę rysowania dla każdego elementu klatki i to właśnie
 * tam najczęściej rozjeżdżał się z trybem graficznym.
 *
 * W przeciwieństwie do wariantu graficznego tekst nie zamalowuje całego okna,
 * więc ekran trzeba wyczyścić jawnie.
 */
final class TextFrameRenderer implements FrameRendererPort
{
    private const CURSOR_HOME = "\e[H";

    private const CLEAR_SCREEN = "\e[2J";

    private const HORIZONTAL = '─';

    private const VERTICAL = '│';

    private const CORNERS = ['╭', '╮', '╰', '╯'];

    private const HAIRLINE = '│';

    private const EDGE = '▌';

    private Theme $theme;

    public function __construct(
        private readonly AnsiPalette $palette,
    ) {
        $this->theme = ThemeService::getInstance()->active();
    }

    public function render(Frame $frame): void
    {
        // Motyw nie jest wstrzykiwany raz przy budowie, tylko pobierany przy
        // każdej klatce — inaczej zmiana palety na ekranie ustawień wymagałaby
        // restartu.
        $this->theme = ThemeService::getInstance()->active();

        $size = TerminalSizeService::getInstance()->size();
        $buffer = new CellBuffer(max(1, $size->rows), max(1, $size->columns));

        foreach ($frame->planes as $plane) {
            if ($plane->opaque) {
                $this->clear($buffer, $plane->bounds);
            }

            foreach ($plane->primitives as $primitive) {
                $this->draw($buffer, $primitive);
            }
        }

        TerminalService::getInstance()->write(
            self::CURSOR_HOME . self::CLEAR_SCREEN . $buffer->toAnsi($this->palette),
        );
    }

    private function draw(CellBuffer $buffer, Primitive $primitive): void
    {
        match (true) {
            $primitive instanceof TextRun => $buffer->write(
                $primitive->row,
                $primitive->column,
                $primitive->text,
                $this->colorOf($primitive->role),
            ),
            $primitive instanceof TextMark => $this->drawTextMark($buffer, $primitive),
            $primitive instanceof RoundRect => $this->drawRoundRect($buffer, $primitive),
            $primitive instanceof Bar => $this->drawBar($buffer, $primitive),
            $primitive instanceof Bitmap => $buffer->write(
                $primitive->bounds->row,
                $primitive->bounds->column,
                $primitive->caption,
                $this->theme->muted,
            ),
            // Nawias narożny i suwak nie mają w siatce znakowej odpowiednika:
            // pierwszy jest ozdobą narożnika, drugi zajmuje pół kolumny.
            default => null,
        };
    }

    /**
     * Podświetlony fragment — **atrybut komórki, nie zmiana treści**, i to jest
     * cała degradacja ósmego prymitywu w siatce znakowej.
     *
     * Tekstowy tryb wychodzi tu lepiej niż w wypadku nawiasu narożnego czy
     * suwaka: tło i kolor pisma to dokładnie te dwa atrybuty, które komórka ma,
     * więc dopasowanie widać co do znaku tak samo, jak w torze graficznym.
     * Odwracanie atrybutów, o którym mówił plan kroku, nie jest przez to
     * potrzebne — kolory przychodzą z motywu i są czytelne z definicji.
     */
    private function drawTextMark(CellBuffer $buffer, TextMark $mark): void
    {
        $ground = $this->colorOf($mark->ground);
        $length = mb_strlen($mark->text);

        for ($offset = 0; $offset < $length; ++$offset) {
            $buffer->paint($mark->row, $mark->column + $offset, $ground);
        }

        $buffer->write($mark->row, $mark->column, $mark->text, $this->colorOf($mark->role));
    }

    /**
     * Wypełnienie maluje tło komórek, obrys — ramkę ze znaków rysunkowych.
     * Znaki łukowe udają zaokrąglenie, więc kształt zgadza się z tym, co
     * w trybie graficznym rysuje Imagick.
     */
    private function drawRoundRect(CellBuffer $buffer, RoundRect $rect): void
    {
        if ($rect->fill !== null) {
            $this->fill($buffer, $rect->bounds, $this->colorOf($rect->fill));
        }

        if ($rect->stroke === null) {
            return;
        }

        $color = $this->colorOf($rect->stroke);
        $bounds = $rect->bounds;
        [$topLeft, $topRight, $bottomLeft, $bottomRight] = self::CORNERS;

        for ($column = $bounds->column + 1; $column < $bounds->right(); ++$column) {
            $buffer->put($bounds->row, $column, self::HORIZONTAL, $color);
            $buffer->put($bounds->bottom(), $column, self::HORIZONTAL, $color);
        }

        for ($row = $bounds->row + 1; $row < $bounds->bottom(); ++$row) {
            $buffer->put($row, $bounds->column, self::VERTICAL, $color);
            $buffer->put($row, $bounds->right(), self::VERTICAL, $color);
        }

        $buffer->put($bounds->row, $bounds->column, $topLeft, $color);
        $buffer->put($bounds->row, $bounds->right(), $topRight, $color);
        $buffer->put($bounds->bottom(), $bounds->column, $bottomLeft, $color);
        $buffer->put($bounds->bottom(), $bounds->right(), $bottomRight, $color);
    }

    private function drawBar(CellBuffer $buffer, Bar $bar): void
    {
        $color = $this->colorOf($bar->role);

        match ($bar->weight) {
            Weight::Hairline => $buffer->put($bar->bounds->row, $bar->bounds->column, self::HAIRLINE, $color),
            Weight::Edge => $buffer->put($bar->bounds->row, $bar->bounds->column, self::EDGE, $color),
            Weight::Fill => $this->fill($buffer, $bar->bounds, $color),
        };
    }

    /**
     * Wymazanie prostokąta płaszczyzny nieprzezroczystej: spacja na tle motywu.
     *
     * Samo przemalowanie tła (`fill()`) tu nie wystarczy — komórka niosłaby
     * dalej znak spod spodu, a płaszczyzna ma zakrywać, nie prześwitywać.
     */
    private function clear(CellBuffer $buffer, Rect $bounds): void
    {
        for ($row = $bounds->row; $row <= $bounds->bottom(); ++$row) {
            for ($column = $bounds->column; $column <= $bounds->right(); ++$column) {
                $buffer->put($row, $column, ' ');
                $buffer->paint($row, $column, $this->colorOf(Role::Background));
            }
        }
    }

    private function fill(CellBuffer $buffer, Rect $bounds, string $color): void
    {
        for ($row = $bounds->row; $row <= $bounds->bottom(); ++$row) {
            for ($column = $bounds->column; $column <= $bounds->right(); ++$column) {
                $buffer->paint($row, $column, $color);
            }
        }
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
}
