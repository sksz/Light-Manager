<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Corner;
use LightManager\Application\Ui\Primitive\CornerBrackets;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\ComponentInterface;

/**
 * Obwódka strefy: zaokrąglony prostokąt, nawiasy w dwóch przeciwległych rogach
 * i etykieta wpięta w górną krawędź.
 *
 * Panel rysuje **samą oprawę**, bez treści — i to jest celowe. Oprawa nie
 * zmienia się między klatkami, dopóki nie zmieni się okno ani motyw, więc leży
 * w osobnej płaszczyźnie, którą renderer podaje z pamięci zamiast rysować od
 * nowa (krok 17, dźwignia 4). Gdyby panel opakowywał dziecko, jego prymitywy
 * byłyby wymieszane z treścią i ta pamięć przestałaby działać.
 *
 * Treść trafia osobno, w prostokąt zwracany przez `inner()`.
 */
final class Panel implements ComponentInterface
{
    /** Oddech między krawędzią okna a obwódką — po jednej kolumnie z każdej strony. */
    public const MARGIN_COLUMNS = 1;

    /** Pierwsza kolumna treści wewnątrz panelu, licząc od krawędzi okna. */
    public const CONTENT_COLUMN = 2;

    /**
     * Odstęp etykiety od lewej krawędzi obwódki. Musi być wyraźnie większy od
     * nawiasu narożnego: postawione obok siebie czytają się jak jeden element
     * („— PATH” zamiast nawiasu i podpisu).
     */
    private const LABEL_COLUMN = 7;

    public function __construct(
        private readonly string $label = '',
        private readonly Role $border = Role::Border,
        private readonly Role $bracket = Role::Accent,
        private readonly Role $labelRole = Role::Muted,
    ) {
    }

    /**
     * Wnętrze panelu: obwódka zjada pierwszy i ostatni wiersz, a po bokach
     * zostaje oddech na tyle szeroki, by pasek zaznaczenia kończył się tam,
     * gdzie zaczyna się ramka, a nie za nią.
     */
    /**
     * Ile znaków etykiety zmieści się na górnej krawędzi panelu tej szerokości.
     *
     * Rachunek nie jest odejmowaniem kolumn i to jest cała jego treść: napis
     * wpięty w linię obwódki renderer rysuje **z rozstrzeleniem** — dokłada około
     * 0,3 kolumny na znak — a po obu stronach wycina jeszcze po kolumnie tła, żeby
     * kreska nie przechodziła przez litery. Etykieta na trzydzieści znaków zajmuje
     * więc czterdzieści kolumn, a nie trzydzieści.
     *
     * Do kroku 24 nikt tego nie potrzebował, bo etykiety były jednym słowem
     * (`PLIKI`, `POMOC`). Podział daje im **ścieżkę katalogu**, a ta bywa dłuższa
     * od panelu — i wtedy różnica między jedną liczbą a drugą to napis wychodzący
     * poza łuk obwódki.
     */
    public static function labelRoom(Rect $bounds): int
    {
        $columns = $bounds->columns - self::LABEL_COLUMN - 2 * self::MARGIN_COLUMNS - 2;

        return max(0, (int) floor($columns / 1.3));
    }

    public static function inner(Rect $bounds): Rect
    {
        return new Rect(
            $bounds->row + 1,
            $bounds->column + self::CONTENT_COLUMN,
            max(0, $bounds->rows - 2),
            max(0, $bounds->columns - 2 * self::CONTENT_COLUMN),
        );
    }

    public function draw(Rect $bounds): array
    {
        // Panel niższy niż trzy wiersze nie ma gdzie postawić obwódki — w takim
        // oknie strefa ustępuje ozdobą, zanim odbierze wiersz treści.
        if ($bounds->rows < 3 || $bounds->columns < 2 * self::MARGIN_COLUMNS + 2) {
            return [];
        }

        $frame = new Rect(
            $bounds->row,
            $bounds->column + self::MARGIN_COLUMNS,
            $bounds->rows,
            $bounds->columns - 2 * self::MARGIN_COLUMNS,
        );

        $primitives = [
            new RoundRect($frame, null, $this->border, Corner::Round),
            new CornerBrackets($frame, $this->bracket, Corner::Round),
        ];

        if ($this->label !== '') {
            $primitives[] = new TextRun(
                $frame->row,
                $frame->column + self::LABEL_COLUMN,
                $this->label,
                $this->labelRole,
                Role::Background,
            );
        }

        return $primitives;
    }
}
