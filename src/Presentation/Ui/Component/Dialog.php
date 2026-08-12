<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Corner;
use LightManager\Application\Ui\Primitive\CornerBrackets;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Application\Ui\Size;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Okno modalne: tytuł i kilka wierszy treści, w oprawie z obwódką i nawiasami.
 *
 * Następca `Popup` z `Domain`. Tamten był jednym z niewielu miejsc, w których
 * ten sam element interfejsu miał **dwie niezależne implementacje** — 49 linii
 * w enkoderze Sixela i 28 w rendererze tekstowym — i to one najczęściej się
 * rozjeżdżały. Tutaj jest jedna, a różnicę między trybami robi renderer,
 * degradując kształty, a nie treść.
 *
 * Okno nie umieszcza się samo: prostokąt wyznacza ten, kto kładzie płaszczyznę.
 * Podpowiedzią jest `size()` — rozmiar, przy którym treść mieści się w całości.
 */
final class Dialog implements ComponentInterface
{
    private const PADDING_COLUMNS = 2;

    /** Wiersz obwódki u góry, wiersz tytułu, wiersz odstępu i wiersz obwódki u dołu. */
    private const CHROME_ROWS = 3;

    /**
     * @param list<string> $lines
     * @param Role         $accent rola tytułu i nawiasów narożnych; okno
     *                             potwierdzenia dla czynności nieodwracalnej
     *                             podaje tu `Danger` (krok 28, D56)
     * @param Role         $border rola obwódki — z tego samego powodu
     */
    public function __construct(
        private readonly string $title,
        private readonly array $lines,
        private readonly Role $accent = Role::Accent,
        private readonly Role $border = Role::Border,
    ) {
    }

    /** Rozmiar, przy którym treść mieści się bez przycinania. */
    public function size(): Size
    {
        $widest = mb_strlen($this->title);

        foreach ($this->lines as $line) {
            $widest = max($widest, mb_strlen($line));
        }

        return new Size(count($this->lines) + self::CHROME_ROWS, $widest + 2 * self::PADDING_COLUMNS);
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->rows < 2 || $bounds->columns < 2) {
            return [];
        }

        $primitives = [
            new RoundRect($bounds, Role::Surface, $this->border, Corner::Round),
            new CornerBrackets($bounds, $this->accent, Corner::Round),
            new TextRun(
                $bounds->row + 1,
                $bounds->column + self::PADDING_COLUMNS,
                Label::fit($this->title, $bounds->columns - 2 * self::PADDING_COLUMNS),
                $this->accent,
            ),
        ];

        foreach ($this->lines as $index => $line) {
            $row = $bounds->row + 2 + $index;

            if ($row >= $bounds->bottom()) {
                break;
            }

            $primitives[] = new TextRun(
                $row,
                $bounds->column + self::PADDING_COLUMNS,
                Label::fit($line, $bounds->columns - 2 * self::PADDING_COLUMNS),
                Role::Text,
            );
        }

        return $primitives;
    }
}
