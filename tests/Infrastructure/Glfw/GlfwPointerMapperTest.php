<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Glfw;

use LightManager\Application\Dto\PointerAction;
use LightManager\Application\Dto\PointerButton;
use LightManager\Infrastructure\Glfw\GlfwPointerMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tor okienkowy sprawdzany **przez mapowanie, bez okna** — tak samo, jak
 * klawiatura od kroku 34 i jak zapowiada punkt 7 planu kroku 55.
 */
final class GlfwPointerMapperTest extends TestCase
{
    /**
     * Wartości stałych rozszerzenia jako literały — stuby `phpgl/ide-stubs`
     * definiują część z nich samymi sobą, więc analiza statyczna nie zna ich
     * typu (reguła 11g). Sam maper porównuje stałe; test podaje liczby, bo
     * gdyby wpisać w oba miejsca to samo źródło, nie sprawdzałby niczego.
     */
    private const RELEASE = 0;

    private const BUTTON_LEFT = 0;

    private const BUTTON_RIGHT = 1;

    private const BUTTON_MIDDLE = 2;

    private const BUTTON_SIDE = 4;

    private const CELL_WIDTH = 9;

    private const CELL_HEIGHT = 18;

    private GlfwPointerMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new GlfwPointerMapper();
    }

    /** @return array<string, array{float, float, int, int}> */
    public static function positions(): array
    {
        return [
            'lewy górny róg to komórka zerowa' => [0.0, 0.0, 0, 0],
            'ostatni piksel pierwszej komórki' => [8.9, 17.9, 0, 0],
            'pierwszy piksel drugiej komórki' => [9.0, 18.0, 1, 1],
            'środek dziesiątej kolumny' => [94.0, 18.0, 1, 10],
            'ujemne położenie ścina się do zera' => [-4.0, -30.0, 0, 0],
        ];
    }

    #[DataProvider('positions')]
    public function testTurnsPixelsIntoCells(float $x, float $y, int $row, int $column): void
    {
        $cell = $this->mapper->cell($x, $y, self::CELL_WIDTH, self::CELL_HEIGHT);

        self::assertSame($row, $cell['row']);
        self::assertSame($column, $cell['column']);
    }

    /**
     * Pierwsza komórka ma numer **zero**, a nie jeden, i to jest cała różnica
     * wobec `GlfwViewportService::cells()`, który liczy rozmiar okna: tam
     * `max(1, …)` jest na miejscu, tutaj byłby błędem przesuwającym cały obraz
     * o wiersz.
     */
    public function testTheFirstCellIsZeroNotOne(): void
    {
        self::assertSame(['row' => 0, 'column' => 0], $this->mapper->cell(1.0, 1.0, 9, 18));
    }

    public function testMapsButtonsToTheDictionary(): void
    {
        self::assertSame(PointerButton::Left, $this->mapper->button(self::BUTTON_LEFT));
        self::assertSame(PointerButton::Right, $this->mapper->button(self::BUTTON_RIGHT));
        self::assertSame(PointerButton::Middle, $this->mapper->button(self::BUTTON_MIDDLE));
    }

    /** Przyciski boczne nie mają odbiorcy (reguła 13), więc nie tworzą zdarzenia. */
    public function testSideButtonsCreateNothing(): void
    {
        self::assertNull($this->mapper->button(self::BUTTON_SIDE));
        self::assertNull($this->mapper->mapButton(self::BUTTON_SIDE, GLFW_PRESS, 0, self::cell()));
    }

    public function testReleaseIsRecognisedByTheAbsenceOfPress(): void
    {
        $pressed = $this->mapper->mapButton(self::BUTTON_LEFT, GLFW_PRESS, 0, self::cell());
        $released = $this->mapper->mapButton(self::BUTTON_LEFT, self::RELEASE, 0, self::cell());

        self::assertNotNull($pressed);
        self::assertNotNull($released);
        self::assertSame(PointerAction::Press, $pressed->action);
        self::assertSame(PointerAction::Release, $released->action);
    }

    /**
     * Ruch bez wciśniętego przycisku **nie tworzy zdarzenia** — zachowanie ma
     * być dokładnie takie, jak w terminalu z trybem `1002`, a nie podobne.
     */
    public function testMotionWithoutAHeldButtonCreatesNothing(): void
    {
        self::assertNull($this->mapper->mapMotion(null, 0, self::cell()));
    }

    public function testMotionWithAHeldButtonIsADrag(): void
    {
        $event = $this->mapper->mapMotion(PointerButton::Right, 0, self::cell());

        self::assertNotNull($event);
        self::assertSame(PointerAction::Drag, $event->action);
        self::assertSame(PointerButton::Right, $event->button);
    }

    public function testScrollDirectionFollowsTheSignOfTheOffset(): void
    {
        $up = $this->mapper->mapScroll(1.0, 0, self::cell());
        $down = $this->mapper->mapScroll(-1.0, 0, self::cell());

        self::assertNotNull($up);
        self::assertNotNull($down);
        self::assertSame(PointerAction::ScrollUp, $up->action);
        self::assertSame(PointerAction::ScrollDown, $down->action);
        self::assertSame(-3, $up->scrollRows());
        self::assertSame(3, $down->scrollRows());
    }

    /** Oś pozioma nie ma odbiorcy — tak samo, jak kółko poziome w terminalu. */
    public function testZeroOffsetCreatesNothing(): void
    {
        self::assertNull($this->mapper->mapScroll(0.0, 0, self::cell()));
    }

    public function testCarriesTheThreeModifiers(): void
    {
        $event = $this->mapper->mapButton(
            self::BUTTON_LEFT,
            GLFW_PRESS,
            GLFW_MOD_CONTROL | GLFW_MOD_ALT | GLFW_MOD_SHIFT,
            self::cell(),
        );

        self::assertNotNull($event);
        self::assertTrue($event->ctrl);
        self::assertTrue($event->alt);
        self::assertTrue($event->shift);
    }

    /** @return array{row: int, column: int} */
    private static function cell(): array
    {
        return ['row' => 4, 'column' => 11];
    }
}
