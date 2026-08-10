<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Primitive\Weight;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Pasek stanu: komunikat po lewej w kolorze swojego tonu, podpowiedzi klawiszy
 * po prawej, między nimi pionowa przegroda.
 *
 * Podpowiedzi ustępują komunikatowi: w wąskim oknie długi błąd jest ważniejszy
 * od przypomnienia, gdzie jest wyjście. Bez tej reguły oba napisy nachodziły na
 * siebie literami.
 */
final class StatusBar implements ComponentInterface
{
    /** Oddech między komunikatem a przegrodą — poniżej niego podpowiedzi znikają. */
    private const GAP_COLUMNS = 2;

    public function __construct(
        private readonly string $message = '',
        private readonly Role $tone = Role::Info,
        private readonly string $hints = '',
    ) {
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        $primitives = [];
        $taken = $bounds->column;

        if ($this->message !== '') {
            $message = Label::fit($this->message, $bounds->columns);
            $primitives[] = new TextRun($bounds->row, $bounds->column, $message, $this->tone);
            $taken += mb_strlen($message);
        }

        if ($this->hints === '') {
            return $primitives;
        }

        $column = $bounds->column + $bounds->columns - mb_strlen($this->hints);

        if ($column < $taken + self::GAP_COLUMNS) {
            return $primitives;
        }

        $primitives[] = new TextRun($bounds->row, $column, $this->hints, Role::Muted);
        $primitives[] = new Bar(new Rect($bounds->row, $column - 1, 1, 1), Role::Border, Weight::Hairline);

        return $primitives;
    }
}
