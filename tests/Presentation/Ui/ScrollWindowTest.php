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

    /**
     * Kółko odczepia okno od kursora (krok 55).
     *
     * Bez odczepienia `keepVisible()` ściągałby okno z powrotem w tej samej
     * klatce, w której kółko je przesunęło — bo panel listowy woła je przy
     * każdym rysowaniu, z tym samym numerem kursora.
     */
    public function testScrollingDetachesTheWindowFromTheCursor(): void
    {
        $window = new ScrollWindow();
        $window->keepVisible(0, 100, 10);

        $window->scrollBy(20);

        self::assertTrue($window->isDetached());
        self::assertSame(20, $window->keepVisible(0, 100, 10), 'okno zostaje tam, gdzie je przesunięto');
    }

    /** Kursor, który się ruszył, przyczepia okno z powrotem. */
    public function testMovingTheCursorReattachesTheWindow(): void
    {
        $window = new ScrollWindow();
        $window->keepVisible(0, 100, 10);
        $window->scrollBy(20);

        // Kursor stanął na pozycji 1, a okno stało na 20 — wraca za nim.
        self::assertSame(1, $window->keepVisible(1, 100, 10), 'okno wróciło do kursora');
        self::assertFalse($window->isDetached());
    }

    /** Odczepione okno nadal nie wyjeżdża poza koniec listy. */
    public function testADetachedWindowStillStopsAtTheEndOfTheList(): void
    {
        $window = new ScrollWindow();
        // Kursor musi być **znany**, zanim okno się odczepi: pierwszy odczyt
        // z nowym numerem przyczepia je z powrotem, i tak ma być.
        $window->keepVisible(0, 100, 10);
        $window->scrollBy(500);

        self::assertSame(90, $window->keepVisible(0, 100, 10));
    }

    /** Zmiana kontekstu przyczepia okno z powrotem — nowy katalog ogląda się od początku. */
    public function testChangingTheContextReattachesTheWindow(): void
    {
        $window = new ScrollWindow();
        $window->scrollBy(20);

        $window->useContext('inny');

        self::assertFalse($window->isDetached());
        self::assertSame(0, $window->offset());
    }
}
