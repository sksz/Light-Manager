<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Diagnostics;

use Imagick;
use ImagickDraw;
use ImagickPixel;
use LightManager\Infrastructure\Imagick\ImagickCapabilityService;

/**
 * Zamienia bajty ANSI trybu tekstowego na obraz — zrzut toru, który obrazu nie
 * rysuje (krok 38, D64).
 *
 * Rozstrzygnięcie użytkownika brzmiało: **zrzut ma być wierny każdemu torowi**.
 * Tor sixelowy oddaje płótno, okno oddaje bufor karty, a tryb tekstowy nie ma
 * czego oddać — jego klatka to znaki i atrybuty. Tańszą drogą byłoby narysowanie
 * tej samej klatki Imagickiem „tak, jak wyglądałaby w Sixelu”, ale zrzut
 * pokazujący, co narysowałby **inny** renderer, nie jest dowodem na to, co
 * narysował ten czynny — a właśnie tego dowodu zabrakło w kroku 29.
 *
 * Rasteryzujemy **bajty**, a nie bufor komórek, i to jest różnica z treścią:
 * w bajtach kolory przeszły już przez `AnsiPalette`, czyli przez zaokrąglenie do
 * 256 albo 16 kolorów terminala. Obraz pokazuje więc barwy, które naprawdę
 * zobaczy użytkownik, a nie te, o które prosił motyw.
 *
 * Czego obraz **nie** pokazuje: prawdziwego fontu terminala. Komórka jest tu
 * stała (8×16), bo pismo dobiera terminal i narzędzie nie ma jak go poznać.
 */
final class AnsiRasterizer
{
    private const CELL_WIDTH = 8;

    private const CELL_HEIGHT = 16;

    /** Sześcian kolorów xterm — te same poziomy, którymi `AnsiPalette` liczy indeks. */
    private const CUBE_LEVELS = [0, 95, 135, 175, 215, 255];

    /** Szesnaście kolorów podstawowych w brzmieniu xterma. */
    private const BASIC = [
        '#000000', '#cd0000', '#00cd00', '#cdcd00', '#0000ee', '#cd00cd', '#00cdcd', '#e5e5e5',
        '#7f7f7f', '#ff0000', '#00ff00', '#ffff00', '#5c5cff', '#ff00ff', '#00ffff', '#ffffff',
    ];

    /**
     * @param string $ansi       bajty w postaci, w jakiej idą do terminala
     * @param string $background kolor tła okna — tam, gdzie ANSI go nie ustawia
     */
    public function rasterize(string $ansi, string $background = '#000000'): Imagick
    {
        $lines = explode("\r\n", $ansi);
        $grid = array_map(fn (string $line): array => $this->cellsOf($line), $lines);
        $columns = 0;

        foreach ($grid as $cells) {
            $columns = max($columns, count($cells));
        }

        $image = new Imagick();
        $image->newImage(
            max(1, $columns * self::CELL_WIDTH),
            max(1, count($grid) * self::CELL_HEIGHT),
            new ImagickPixel($background),
            'png',
        );

        $this->paint($image, $grid);

        return $image;
    }

    /**
     * Jeden wiersz rozłożony na komórki: znak plus kolory obowiązujące w chwili,
     * gdy padł.
     *
     * Sekwencje sterujące rozbieramy sami, bo format jest **nasz**:
     * `CellBuffer::toAnsi()` wypisuje wyłącznie `0m`, `38;5;N`, `48;5;N` oraz
     * kody podstawowe 30–37/40–47/90–97/100–107. Pełny parser terminala byłby
     * tu odpowiedzią na pytanie, którego nikt nie zadał.
     *
     * @return list<array{string, ?string, ?string}> znak, pismo, tło
     */
    private function cellsOf(string $line): array
    {
        $cells = [];
        $foreground = null;
        $background = null;
        $offset = 0;
        $length = strlen($line);

        while ($offset < $length) {
            // Każdą sekwencję sterującą **zjadamy w całości**, a kolory
            // wyciągamy tylko z tych zakończonych `m`. Bez tego ustawienie
            // kursora i wyczyszczenie ekranu wypisałyby się w obrazie jako
            // „[H [2J” — czyli zrzut pokazywałby coś, czego na ekranie nie ma.
            if ($line[$offset] === "\e" && preg_match('/\G\e\[([\d;]*)([a-zA-Z])/', $line, $matches, 0, $offset) === 1) {
                if ($matches[2] === 'm') {
                    [$foreground, $background] = $this->applied($matches[1], $foreground, $background);
                }

                $offset += strlen($matches[0]);

                continue;
            }

            $character = $this->characterAt($line, $offset);
            $cells[] = [$character, $foreground, $background];
            $offset += strlen($character);
        }

        return $cells;
    }

    /**
     * Kody SGR jednej sekwencji przełożone na parę kolorów.
     *
     * @param ?string $foreground kolor pisma sprzed sekwencji
     * @param ?string $background kolor tła sprzed sekwencji
     *
     * @return array{?string, ?string}
     */
    private function applied(string $parameters, ?string $foreground, ?string $background): array
    {
        $codes = array_map('intval', $parameters === '' ? ['0'] : explode(';', $parameters));

        for ($index = 0; $index < count($codes); ++$index) {
            $code = $codes[$index];

            // `38;5;N` i `48;5;N` — kolor z palety 256, trzy liczby na sekwencję.
            if (($code === 38 || $code === 48) && ($codes[$index + 1] ?? null) === 5) {
                $color = $this->fromIndex($codes[$index + 2] ?? 0);
                $code === 38 ? $foreground = $color : $background = $color;
                $index += 2;

                continue;
            }

            [$foreground, $background] = match (true) {
                $code === 0 => [null, null],
                $code >= 30 && $code <= 37 => [self::BASIC[$code - 30], $background],
                $code >= 90 && $code <= 97 => [self::BASIC[$code - 90 + 8], $background],
                $code >= 40 && $code <= 47 => [$foreground, self::BASIC[$code - 40]],
                $code >= 100 && $code <= 107 => [$foreground, self::BASIC[$code - 100 + 8]],
                default => [$foreground, $background],
            };
        }

        return [$foreground, $background];
    }

    /** Indeks palety 256 przełożony na kolor — odwrotność `AnsiPalette::index256()`. */
    private function fromIndex(int $index): string
    {
        if ($index < 16) {
            return self::BASIC[max(0, $index)];
        }

        if ($index >= 232) {
            $gray = 8 + ($index - 232) * 10;

            return sprintf('#%02x%02x%02x', $gray, $gray, $gray);
        }

        $offset = $index - 16;

        return sprintf(
            '#%02x%02x%02x',
            self::CUBE_LEVELS[intdiv($offset, 36) % 6],
            self::CUBE_LEVELS[intdiv($offset, 6) % 6],
            self::CUBE_LEVELS[$offset % 6],
        );
    }

    /** Znak wielobajtowy zabrany w całości — inaczej ramka rozsypałaby się na bajty. */
    private function characterAt(string $line, int $offset): string
    {
        $character = substr($line, $offset, 4);
        $decoded = mb_substr($character, 0, 1, 'UTF-8');

        return $decoded === '' ? substr($line, $offset, 1) : $decoded;
    }

    /**
     * Tła malujemy **ciągami**, a nie komórka po komórce: wiersz listy o
     * jednolitym kolorze to jeden prostokąt zamiast stu sześćdziesięciu.
     *
     * @param list<list<array{string, ?string, ?string}>> $grid
     */
    private function paint(Imagick $image, array $grid): void
    {
        $draw = new ImagickDraw();
        $draw->setFont(ImagickCapabilityService::getInstance()->monospaceFont() ?? 'DejaVu-Sans-Mono');
        $draw->setFontSize(self::CELL_HEIGHT * 0.8);
        $draw->setStrokeWidth(0);

        foreach ($grid as $row => $cells) {
            $this->paintBackgrounds($draw, $row, $cells);
            $this->paintGlyphs($draw, $row, $cells);
        }

        $image->drawImage($draw);
    }

    /** @param list<array{string, ?string, ?string}> $cells */
    private function paintBackgrounds(ImagickDraw $draw, int $row, array $cells): void
    {
        $start = 0;
        $current = null;

        foreach ([...$cells, ['', null, "\x00"]] as $column => [, , $background]) {
            if ($background === $current) {
                continue;
            }

            if ($current !== null && $column > $start) {
                $draw->setFillColor(new ImagickPixel($current));
                $draw->rectangle(
                    $start * self::CELL_WIDTH,
                    $row * self::CELL_HEIGHT,
                    $column * self::CELL_WIDTH - 1,
                    ($row + 1) * self::CELL_HEIGHT - 1,
                );
            }

            $current = $background === "\x00" ? null : $background;
            $start = $column;
        }
    }

    /** @param list<array{string, ?string, ?string}> $cells */
    private function paintGlyphs(ImagickDraw $draw, int $row, array $cells): void
    {
        $baseline = ($row + 1) * self::CELL_HEIGHT - 4;

        foreach ($cells as $column => [$character, $foreground]) {
            if ($character === ' ' || $character === '') {
                continue;
            }

            $draw->setFillColor(new ImagickPixel($foreground ?? '#c8ccd4'));
            $draw->annotation($column * self::CELL_WIDTH, $baseline, $character);
        }
    }
}
