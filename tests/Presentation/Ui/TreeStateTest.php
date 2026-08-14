<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui;

use LightManager\Presentation\Ui\TreeState;
use PHPUnit\Framework\TestCase;

/**
 * Czwarta klasa stanu między klatkami — sprawdzana dokładnie tam, gdzie różni
 * się od trzech poprzednich.
 *
 * Trzy rzeczy są tu treścią, a nie szczegółem: rozwinięcie pamięta się **pod
 * kluczem**, kursor **też jest kluczem**, a zwinięcie gałęzi **przenosi na nią
 * kursor**. Każda z nich jest rozstrzygnięciem ze startu kroku 31 i każda ma tu
 * swój test.
 */
final class TreeStateTest extends TestCase
{
    public function testBranchesStartCollapsed(): void
    {
        $state = new TreeState();

        self::assertFalse($state->isExpanded('/home'), 'gałąź bez wpisu jest zwinięta — odwrotnie niż sekcja');
    }

    public function testTogglingSwitchesTheBranchBothWays(): void
    {
        $state = new TreeState();

        $state->toggle('/home');
        self::assertTrue($state->isExpanded('/home'));

        $state->toggle('/home');
        self::assertFalse($state->isExpanded('/home'));
    }

    /**
     * Główna obietnica klucza: gałąź, która zniknęła z widoku i wróciła, wraca
     * rozwinięta. To jest kryterium kroku wyrażone najkrócej, jak się da.
     */
    public function testExpansionSurvivesAChangeOfContext(): void
    {
        $state = new TreeState();
        $state->expand('/home/projekty');

        $state->useContext('/etc');
        $state->useContext('/home');

        self::assertTrue($state->isExpanded('/home/projekty'));
    }

    public function testChangingContextStartsTheCursorAfresh(): void
    {
        $state = new TreeState();
        $state->moveTo('/home/plik.txt');

        $state->useContext('/etc');

        self::assertNull($state->cursor());
    }

    public function testSameContextLeavesTheCursorAlone(): void
    {
        $state = new TreeState();
        $state->useContext('/home');
        $state->moveTo('/home/plik.txt');

        $state->useContext('/home');

        self::assertSame('/home/plik.txt', $state->cursor());
    }

    /** Rozstrzygnięcie nr 2 kroku: po zwinięciu kursor stoi na zwiniętej gałęzi. */
    public function testCollapsingPutsTheCursorOnTheCollapsedBranch(): void
    {
        $state = new TreeState();
        $state->expand('/home/projekty');
        $state->moveTo('/home/projekty/lm/src');

        $state->collapse('/home/projekty');

        self::assertSame('/home/projekty', $state->cursor());
    }

    public function testMovingWalksTheVisibleKeysInOrder(): void
    {
        $keys = ['/a', '/b', '/c'];
        $state = new TreeState();
        $state->moveTo('/a');

        $state->moveBy(2, $keys);
        self::assertSame('/c', $state->cursor());

        $state->moveBy(-1, $keys);
        self::assertSame('/b', $state->cursor());
    }

    public function testMovingStopsAtBothEndsOfTheTree(): void
    {
        $keys = ['/a', '/b'];
        $state = new TreeState();
        $state->moveTo('/a');

        $state->moveBy(-5, $keys);
        self::assertSame('/a', $state->cursor());

        $state->moveBy(9, $keys);
        self::assertSame('/b', $state->cursor());
    }

    /**
     * Wołanie z zerem znaczy „drzewo się zmieniło, ustaw się w jego granicach” —
     * ta sama umowa, co w `SectionState::moveBy()`.
     */
    public function testCursorPointingAtAVanishedNodeFallsBackToTheFirstOne(): void
    {
        $state = new TreeState();
        $state->moveTo('/home/zniknelo');

        $state->moveBy(0, ['/home/a', '/home/b']);

        self::assertSame('/home/a', $state->cursor());
    }

    public function testEmptyTreeLeavesNoCursorAtAll(): void
    {
        $state = new TreeState();
        $state->moveTo('/home/a');

        $state->moveBy(0, []);

        self::assertNull($state->cursor());
        self::assertNull($state->indexIn([]));
    }

    public function testIndexIsCountedInTheVisibleKeys(): void
    {
        $state = new TreeState();
        $state->moveTo('/b');

        self::assertSame(1, $state->indexIn(['/a', '/b', '/c']));
        self::assertNull($state->indexIn(['/a', '/c']), 'węzeł poza drzewem nie ma numeru');
    }
}
