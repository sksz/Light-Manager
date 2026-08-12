<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui;

use LightManager\Presentation\Ui\ScrollWindow;
use PHPUnit\Framework\TestCase;

/**
 * Okno przewijania wobec pojemności zmieniającej się między klatkami.
 *
 * Do kroku 33 pojemność panelu mogła się zmienić tylko razem z układem stref
 * (inny ekran, zwinięta sekcja); od kroku 33 zmienia ją także samo okno
 * terminala — i to jest przypadek, którego pilnują te testy: przewinięcie
 * sprzed zmiany nie ma prawa zostawić kursora poza ekranem ani pokazać pustki
 * tam, gdzie jest jeszcze treść.
 */
final class ScrollWindowTest extends TestCase
{
    public function testShrinkingCapacityKeepsTheCursorVisible(): void
    {
        $window = new ScrollWindow();

        // Okno 20 wierszy, kursor na samym dole setki wpisów.
        $window->keepVisible(99, 100, 20);
        self::assertSame(80, $window->offset());

        // Okno skurczyło się do 5 wierszy — kursor ma zostać w widoku.
        $offset = $window->keepVisible(99, 100, 5);

        self::assertSame(95, $offset);
        self::assertLessThanOrEqual(99, $offset + 4);
    }

    public function testGrowingCapacityPullsTheWindowBackFromBeyondTheList(): void
    {
        $window = new ScrollWindow();
        $window->keepVisible(99, 100, 10);
        self::assertSame(90, $window->offset());

        // Okno urosło do 50 wierszy: przewinięcie 90 pokazałoby 10 wpisów
        // i 40 wierszy pustki, więc górna granica musi je ściągnąć w dół.
        self::assertSame(50, $window->clamp(100, 50));
    }

    public function testWindowTallerThanTheListStartsFromTheTop(): void
    {
        $window = new ScrollWindow();
        $window->keepVisible(99, 100, 10);

        self::assertSame(0, $window->clamp(100, 120));
    }

    public function testDegenerateCapacityCollapsesToTheTop(): void
    {
        $window = new ScrollWindow();
        $window->keepVisible(99, 100, 10);

        // Okno tak niskie, że panel nie mieści ani wiersza — jak przy oknie
        // terminala ściśniętym do paska stanu.
        self::assertSame(0, $window->clamp(100, 0));
    }

    public function testMarginShrinksTogetherWithTheCapacity(): void
    {
        $window = new ScrollWindow(margin: 3);

        // Pojemność 3 nie pomieści kursora z marginesem 3 po obu stronach —
        // margines ścina się do połowy okna, a rachunek nie może się wywrócić.
        $offset = $window->keepVisible(50, 100, 3);

        self::assertGreaterThanOrEqual($offset, 50);
        self::assertLessThanOrEqual($offset + 2, 50);
    }
}
