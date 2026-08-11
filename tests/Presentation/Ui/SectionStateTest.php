<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui;

use LightManager\Presentation\Ui\SectionState;
use PHPUnit\Framework\TestCase;

/**
 * Pamięć listy sekcji między klatkami (krok 22).
 *
 * Druga taka klasa w projekcie po `ScrollWindow` i sprawdzana z tego samego
 * powodu: komponent powstaje na nowo co klatkę, więc jeśli coś ma przeżyć, to
 * wyłącznie tutaj — a błąd w tym miejscu widać dopiero po kilku naciśnięciach.
 */
final class SectionStateTest extends TestCase
{
    public function testNothingIsCollapsedUntilItIsToggled(): void
    {
        $state = new SectionState();

        self::assertFalse($state->isCollapsed('rozmiar'));

        $state->toggle('rozmiar');

        self::assertTrue($state->isCollapsed('rozmiar'));

        $state->toggle('rozmiar');

        self::assertFalse($state->isCollapsed('rozmiar'));
    }

    public function testCollapsingOneSectionLeavesTheOthersAlone(): void
    {
        $state = new SectionState();
        $state->toggle('rozmiar');

        self::assertTrue($state->isCollapsed('rozmiar'));
        self::assertFalse($state->isCollapsed('czasy'));
    }

    /**
     * Sedno klucza-napisu: sekcja, która zniknęła z listy i wróciła, wraca
     * zwinięta. Pod numerem stan przeszedłby na sąsiada.
     */
    public function testCollapseSurvivesTheSectionDisappearingAndComingBack(): void
    {
        $state = new SectionState();
        $state->toggle('screen.file-info');
        $state->useContext('inna zakładka');
        $state->useContext('ta sama co przedtem');

        self::assertTrue($state->isCollapsed('screen.file-info'));
    }

    public function testCursorMovesAndStopsAtBothEndsOfTheList(): void
    {
        $state = new SectionState();

        $state->moveBy(-1, 3);
        self::assertSame(0, $state->cursor(), 'w górę z pierwszej sekcji nie ma dokąd');

        $state->moveBy(2, 3);
        self::assertSame(2, $state->cursor());

        $state->moveBy(1, 3);
        self::assertSame(2, $state->cursor(), 'w dół z ostatniej sekcji nie ma dokąd');
    }

    public function testShorterListPullsTheCursorBackIntoItsBounds(): void
    {
        $state = new SectionState();
        $state->moveBy(5, 6);

        self::assertSame(5, $state->cursor());

        // Moduł wyłączony — sekcji ubyło, a kursor nie ruszył się sam z siebie.
        $state->moveBy(0, 2);

        self::assertSame(1, $state->cursor());
    }

    public function testEmptyListPutsTheCursorAtZero(): void
    {
        $state = new SectionState();
        $state->moveBy(3, 5);
        $state->moveBy(0, 0);

        self::assertSame(0, $state->cursor());
    }

    public function testChangingContextStartsFromTheTop(): void
    {
        $state = new SectionState();
        $state->moveBy(2, 5);
        $state->useContext('zakładka modułu');

        self::assertSame(0, $state->cursor());
    }

    public function testTheSameContextTwiceLeavesTheCursorWhereItWas(): void
    {
        $state = new SectionState();
        $state->useContext('sterowanie');
        $state->moveBy(2, 5);
        $state->useContext('sterowanie');

        self::assertSame(2, $state->cursor(), 'klatka za klatką nie ma prawa cofać kursora');
    }
}
