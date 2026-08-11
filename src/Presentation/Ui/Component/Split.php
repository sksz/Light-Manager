<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Rect;
use LightManager\Presentation\Ui\ComponentInterface;
use LightManager\Presentation\Ui\SplitAxis;

/**
 * Podział prostokąta na dwa i oddanie ich dwojgu dzieciom.
 *
 * Podział **nie tworzy dwóch ekranów** — tworzy dwa miejsca wewnątrz jednego.
 * Widoczny ekran jest nadal dokładnie jeden: `F1`, `F2` i skrót modułu zastępują
 * go w całości, razem z jego podziałem, a `ScreenStack` o niczym nie musi
 * wiedzieć. To rozstrzygnięcie użytkownika ze startu kroku 24 i to ono
 * zdecydowało, że krok nie dotyka ani stosu ekranów, ani `ScreenInterface`, ani
 * rozdzielania klawiszy.
 *
 * Sam komponent jest wyłącznie geometrią: liczy dwa prostokąty i woła dzieci.
 * Granicy nie rysuje, bo nie ma czego — dzisiejszy jedyny użytkownik daje po obu
 * stronach panele z własnymi obwódkami, a te stykają się w środku i **są**
 * granicą. Kreska dorysowana między nimi byłaby trzecią linią obok dwóch już
 * narysowanych.
 *
 * Progi są tu, a nie w `HudLayout`, bo podział należy do ekranu, a nie do okna —
 * ale mają tę samą naturę, co progi wysokości stref: **nie wynikają z arytmetyki,
 * tylko z tego, co się jeszcze da czytać**. Dwa panele w oknie na 60 kolumn
 * mieszczą się bez reszty i mimo to nie powstają.
 */
final class Split implements ComponentInterface
{
    /**
     * Poniżej tylu kolumn granica pionowa nie powstaje.
     *
     * Przy 72 kolumnach każdy panel dostaje około 33 kolumn treści — tyle, ile
     * potrzeba na nazwę pliku i rozmiar obok niej. Niżej nazwy zaczynają się
     * urywać wielokropkiem w **obu** panelach naraz, a lista plików, w której nie
     * widać nazw, przestaje być listą plików.
     */
    public const MINIMUM_COLUMNS = 72;

    /**
     * Poniżej tylu wierszy granica pozioma nie powstaje.
     *
     * Przy 14 wierszach każdy panel ma 7, z czego obwódka zabiera 2 — zostaje
     * pięć wierszy treści, czyli tyle, żeby ruch zaznaczenia w ogóle było widać.
     */
    public const MINIMUM_ROWS = 14;

    public function __construct(
        private readonly ComponentInterface $first,
        private readonly ComponentInterface $second,
        private readonly SplitAxis $axis = SplitAxis::Vertical,
        /** Jaka część prostokąta przypada pierwszemu dziecku. */
        private readonly float $fraction = 0.5,
    ) {
    }

    /**
     * Czy prostokąt jest dość duży, żeby podział miał sens.
     *
     * Statyczne, bo pyta o to ekran **zanim** zbuduje dzieci: gdy podział nie
     * powstaje, drugi panel nie ma się z czego złożyć i nie ma po co.
     */
    public static function fits(Rect $bounds, SplitAxis $axis): bool
    {
        if ($bounds->isEmpty()) {
            return false;
        }

        return $axis === SplitAxis::Vertical
            ? $bounds->columns >= self::MINIMUM_COLUMNS
            : $bounds->rows >= self::MINIMUM_ROWS;
    }

    /**
     * Dwa prostokąty, na które rozpada się podany.
     *
     * Wystawione osobno, bo jest to jedyna liczbowa treść tej klasy i jedyne, co
     * da się sprawdzić testem bez rysowania czegokolwiek — dokładnie ta sama
     * decyzja, którą podjął `VStack::distribute()`.
     *
     * @return array{Rect, Rect}
     */
    public static function halves(Rect $bounds, SplitAxis $axis, float $fraction = 0.5): array
    {
        $fraction = max(0.0, min(1.0, $fraction));

        if ($axis === SplitAxis::Vertical) {
            $width = (int) round($bounds->columns * $fraction);

            return [
                new Rect($bounds->row, $bounds->column, $bounds->rows, $width),
                new Rect($bounds->row, $bounds->column + $width, $bounds->rows, $bounds->columns - $width),
            ];
        }

        $height = (int) round($bounds->rows * $fraction);

        return [
            new Rect($bounds->row, $bounds->column, $height, $bounds->columns),
            new Rect($bounds->row + $height, $bounds->column, $bounds->rows - $height, $bounds->columns),
        ];
    }

    public function draw(Rect $bounds): array
    {
        if ($bounds->isEmpty()) {
            return [];
        }

        [$first, $second] = self::halves($bounds, $this->axis, $this->fraction);
        $primitives = [];

        foreach ([[$this->first, $first], [$this->second, $second]] as [$child, $rect]) {
            foreach ($child->draw($rect) as $primitive) {
                $primitives[] = $primitive;
            }
        }

        return $primitives;
    }
}
