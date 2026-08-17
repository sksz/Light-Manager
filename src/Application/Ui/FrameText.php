<?php

declare(strict_types=1);

namespace LightManager\Application\Ui;

use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Bitmap;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\TextMark;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Primitive\Weight;

/**
 * **Drugie oblicze klatki: to samo, co widać, zapisane znakami** (krok 56).
 *
 * Rachunek „prymitywy → siatka znaków” stał od kroku 18 w
 * `Infrastructure\Rendering\TextFrameRenderer::composeBuffer()` i był tam
 * własnością **jednego toru**: dwa pozostałe — sixelowy i okienkowy — nie miały
 * jak go zawołać, bo renderer po drugiej stronie portu jest dla nich obcą
 * implementacją. Dopóki jedynym pytaniem było „co wypisać na terminal”, nie
 * przeszkadzało to nikomu. Zaznaczanie treści pyta o co innego — **co na klatce
 * pisze** — i to pytanie jest wspólne wszystkim trzem torom, bo zaznaczenie
 * musi w każdym z nich oddać ten sam napis.
 *
 * Miejscem jest `Application/Ui`, bo tu mieszkają prymitywy, a rachunek nie zna
 * ani terminala, ani okna, ani motywu: **niesie role, nie kolory**. Renderer
 * tekstowy nakłada na niego paletę i zostaje tym, czym był.
 *
 * **Drugi, uproszczony rachunek obok tego był odrzucony z góry** (zakres kroku,
 * punkt 1): dwie kopie odwzorowania prymitywu na znak rozjechałyby się przy
 * pierwszym nowym kształcie, a rozjazd byłby **niewidoczny** — zaznaczenie
 * oddawałoby inny tekst niż ten na ekranie, i to wyłącznie w tym jednym
 * kształcie, którego nikt nie sprawdził.
 *
 * Degradacja kształtów jest ta sama, co miał renderer tekstowy, i taka ma
 * zostać: obwódka to znaki rysunkowe, miniatura to sam podpis, a nawias narożny
 * i suwak **nie mają w siatce znakowej odpowiednika** — pierwszy jest ozdobą
 * narożnika, drugi zajmuje pół kolumny. Zaznaczenie przeciągnięte przez
 * miniaturę bierze przez to jej podpis, a nie obraz, i jest to jedyna uczciwa
 * odpowiedź: obrazu nie da się wkleić do pola tekstowego.
 */
final class FrameText
{
    private const BLANK = ' ';

    private const HORIZONTAL = '─';

    private const VERTICAL = '│';

    private const CORNERS = ['╭', '╮', '╰', '╯'];

    private const HAIRLINE = '│';

    private const EDGE = '▌';

    /** @var array<int, array<int, string>> znak w każdej komórce */
    private array $glyphs = [];

    /** @var array<int, array<int, ?Role>> rola pisma; `null` — domyślna */
    private array $foreground = [];

    /** @var array<int, array<int, ?Role>> rola tła; `null` — przezroczyste */
    private array $background = [];

    public function __construct(
        public readonly int $rows,
        public readonly int $columns,
    ) {
        for ($row = 0; $row < $this->rows; ++$row) {
            $this->glyphs[$row] = array_fill(0, max(1, $this->columns), self::BLANK);
            $this->foreground[$row] = array_fill(0, max(1, $this->columns), null);
            $this->background[$row] = array_fill(0, max(1, $this->columns), null);
        }
    }

    /**
     * Klatka rozłożona na znaki — **jedno przejście po prymitywach**, w tej samej
     * kolejności, w jakiej rysują je renderery.
     *
     * Płaszczyzna nieprzezroczysta zaczyna od wymazania swojego prostokąta, bo
     * ma zakrywać to, co pod nią leży (`Plane::$opaque`). Samo przemalowanie tła
     * tu nie wystarcza — komórka niosłaby dalej znak spod spodu.
     */
    public static function of(Frame $frame, int $rows, int $columns): self
    {
        $text = new self(max(1, $rows), max(1, $columns));

        foreach ($frame->planes as $plane) {
            if ($plane->opaque) {
                $text->clear($plane->bounds);
            }

            foreach ($plane->primitives as $primitive) {
                $text->draw($primitive);
            }
        }

        return $text;
    }

    /** Znak stojący w komórce; spoza siatki — spacja, bo nie ma tam nic. */
    public function glyph(int $row, int $column): string
    {
        return $this->glyphs[$row][$column] ?? self::BLANK;
    }

    public function foreground(int $row, int $column): ?Role
    {
        return $this->foreground[$row][$column] ?? null;
    }

    public function background(int $row, int $column): ?Role
    {
        return $this->background[$row][$column] ?? null;
    }

    /**
     * Cały wiersz naraz — dla tego, kto i tak przechodzi po każdej komórce.
     *
     * Trzy metody zamiast trzech tysięcy wywołań: renderer tekstowy składa
     * z siatki bajty, więc pyta o **każdą** komórkę, a pytanie po jednej
     * kosztowałoby w dużym oknie tyle, ile sam rachunek. Tablicy nie da się przy
     * tym z zewnątrz zepsuć — PHP oddaje ją przez wartość.
     *
     * @return array<int, string>
     */
    public function glyphRow(int $row): array
    {
        return $this->glyphs[$row] ?? [];
    }

    /** @return array<int, ?Role> */
    public function foregroundRow(int $row): array
    {
        return $this->foreground[$row] ?? [];
    }

    /** @return array<int, ?Role> */
    public function backgroundRow(int $row): array
    {
        return $this->background[$row] ?? [];
    }

    /**
     * Napis stojący w prostokącie — **wiersz po wierszu, z obcięciem spacji na
     * końcu**.
     *
     * Obcinamy wyłącznie z prawej i jest to rozstrzygnięcie, nie skrót: klatka
     * jest wyrównana do kolumn, więc bez obcięcia każdy wiersz kończyłby się
     * smugą spacji sięgającą krawędzi prostokąta. Spacje **wiodące** zostają, bo
     * niosą kształt bloku — wcięcie drzewa, wyrównanie kolumny, odstęp od
     * obwódki — a zaznaczenie jest prostokątne właśnie po to, żeby wziąć to, co
     * użytkownik obrysował.
     *
     * @return list<string> tyle napisów, ile wierszy prostokąta leży na siatce
     */
    public function textIn(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $lines = [];

        for ($row = max(0, $bounds->row); $row <= min($this->rows - 1, $bounds->bottom()); ++$row) {
            $line = '';

            for ($column = max(0, $bounds->column); $column <= min($this->columns - 1, $bounds->right()); ++$column) {
                $line .= $this->glyphs[$row][$column];
            }

            $lines[] = rtrim($line);
        }

        return $lines;
    }

    private function draw(Primitive $primitive): void
    {
        match (true) {
            $primitive instanceof TextRun => $this->write(
                $primitive->row,
                $primitive->column,
                $primitive->text,
                $primitive->role,
            ),
            $primitive instanceof TextMark => $this->drawTextMark($primitive),
            $primitive instanceof RoundRect => $this->drawRoundRect($primitive),
            $primitive instanceof Bar => $this->drawBar($primitive),
            // Miniatura zostaje samym podpisem — to jedyne, co da się z niej
            // przeczytać.
            $primitive instanceof Bitmap => $this->write(
                $primitive->bounds->row,
                $primitive->bounds->column,
                $primitive->caption,
                Role::Muted,
            ),
            // Nawias narożny i suwak nie mają w siatce znakowej odpowiednika.
            default => null,
        };
    }

    /**
     * Podświetlony fragment — **atrybut komórki, nie zmiana treści**.
     *
     * Znak zostaje tam, gdzie był; zmieniają się dwie role tej samej komórki.
     * Gdyby było inaczej, zaznaczenie przeciągnięte przez dopasowanie filtra
     * oddawałoby inny napis niż ten, który widać.
     */
    private function drawTextMark(TextMark $mark): void
    {
        $length = mb_strlen($mark->text);

        for ($offset = 0; $offset < $length; ++$offset) {
            $this->paint($mark->row, $mark->column + $offset, $mark->ground);
        }

        $this->write($mark->row, $mark->column, $mark->text, $mark->role);
    }

    /** Wypełnienie maluje tło komórek, obrys — ramkę ze znaków rysunkowych. */
    private function drawRoundRect(RoundRect $rect): void
    {
        if ($rect->fill !== null) {
            $this->fill($rect->bounds, $rect->fill);
        }

        if ($rect->stroke === null) {
            return;
        }

        $role = $rect->stroke;
        $bounds = $rect->bounds;
        [$topLeft, $topRight, $bottomLeft, $bottomRight] = self::CORNERS;

        for ($column = $bounds->column + 1; $column < $bounds->right(); ++$column) {
            $this->put($bounds->row, $column, self::HORIZONTAL, $role);
            $this->put($bounds->bottom(), $column, self::HORIZONTAL, $role);
        }

        for ($row = $bounds->row + 1; $row < $bounds->bottom(); ++$row) {
            $this->put($row, $bounds->column, self::VERTICAL, $role);
            $this->put($row, $bounds->right(), self::VERTICAL, $role);
        }

        $this->put($bounds->row, $bounds->column, $topLeft, $role);
        $this->put($bounds->row, $bounds->right(), $topRight, $role);
        $this->put($bounds->bottom(), $bounds->column, $bottomLeft, $role);
        $this->put($bounds->bottom(), $bounds->right(), $bottomRight, $role);
    }

    private function drawBar(Bar $bar): void
    {
        match ($bar->weight) {
            Weight::Hairline => $this->put($bar->bounds->row, $bar->bounds->column, self::HAIRLINE, $bar->role),
            Weight::Edge => $this->put($bar->bounds->row, $bar->bounds->column, self::EDGE, $bar->role),
            Weight::Fill => $this->fill($bar->bounds, $bar->role),
        };
    }

    private function clear(Rect $bounds): void
    {
        for ($row = $bounds->row; $row <= $bounds->bottom(); ++$row) {
            for ($column = $bounds->column; $column <= $bounds->right(); ++$column) {
                $this->put($row, $column, self::BLANK);
                $this->paint($row, $column, Role::Background);
            }
        }
    }

    private function fill(Rect $bounds, Role $role): void
    {
        for ($row = $bounds->row; $row <= $bounds->bottom(); ++$row) {
            for ($column = $bounds->column; $column <= $bounds->right(); ++$column) {
                $this->paint($row, $column, $role);
            }
        }
    }

    private function put(int $row, int $column, string $glyph, ?Role $role = null): void
    {
        if ($row < 0 || $row >= $this->rows || $column < 0 || $column >= $this->columns) {
            return;
        }

        $this->glyphs[$row][$column] = $glyph;

        if ($role !== null) {
            $this->foreground[$row][$column] = $role;
        }
    }

    private function write(int $row, int $column, string $text, ?Role $role = null): void
    {
        $length = mb_strlen($text);

        for ($offset = 0; $offset < $length; ++$offset) {
            $this->put($row, $column + $offset, mb_substr($text, $offset, 1), $role);
        }
    }

    /** Tło komórki; znak zostaje nietknięty, więc tekst położony wcześniej przetrwa. */
    private function paint(int $row, int $column, Role $role): void
    {
        if ($row < 0 || $row >= $this->rows || $column < 0 || $column >= $this->columns) {
            return;
        }

        $this->background[$row][$column] = $role;
    }
}
