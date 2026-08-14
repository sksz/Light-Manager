<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Presentation\Ui\Component\TreeNode;
use LightManager\Presentation\Ui\Component\TreeView;
use PHPUnit\Framework\TestCase;

/**
 * Drzewo jako komponent (krok 31): wcięcie, prowadnice, znacznik gałęzi i okno.
 *
 * Sprawdzian idzie **dwiema drogami naraz** i jest to celowe. Wcięcie mierzy się
 * przez `prefixOf()`, bo to jest cała treść tej klasy i porównywanie prymitywów
 * mierzyłoby przy okazji `ListView` i `Label`. Reszta — wycinek, kursor, suwak —
 * przez narysowaną klatkę, bo tam właśnie te trzy rzeczy się spotykają.
 */
final class TreeViewTest extends TestCase
{
    public function testRootLevelHangsOffNothingButStillCarriesItsConnector(): void
    {
        $first = new TreeNode('/a', 'a', [], false, true, false);
        $last = new TreeNode('/z', 'z', [], true);

        self::assertSame(TreeView::BRANCH . TreeView::CLOSED . ' ', TreeView::prefixOf($first));
        self::assertSame(TreeView::LAST . TreeView::LEAF . ' ', TreeView::prefixOf($last));
    }

    /**
     * Prowadnica biegnie na poziomie przodka, który ma jeszcze rodzeństwo, a na
     * poziomie domkniętym zostaje puste wcięcie. To jest cały powód, dla którego
     * `TreeNode` niesie `guides`, a nie samą głębokość.
     */
    public function testGuidesDrawATrunkOnlyUnderAnAncestorWithSiblingsLeft(): void
    {
        $node = new TreeNode('/a/b/c', 'c', [true, false], true);

        self::assertSame(
            TreeView::TRUNK . TreeView::CLEAR . TreeView::LAST . TreeView::LEAF . ' ',
            TreeView::prefixOf($node),
        );
        self::assertSame(2, $node->depth(), 'głębokość jest długością prowadnic, a nie osobnym polem');
    }

    public function testMarkerTellsAnOpenBranchFromAClosedOneAndFromALeaf(): void
    {
        $open = new TreeNode('/a', 'a', [], true, true, true);
        $closed = new TreeNode('/b', 'b', [], true, true);
        $leaf = new TreeNode('/c', 'c', [], true);

        self::assertStringEndsWith(TreeView::OPEN . ' ', TreeView::prefixOf($open));
        self::assertStringEndsWith(TreeView::CLOSED . ' ', TreeView::prefixOf($closed));
        self::assertStringEndsWith(TreeView::LEAF . ' ', TreeView::prefixOf($leaf));
    }

    /**
     * Znacznik liścia zajmuje **tyle samo miejsca**, co znacznik gałęzi — inaczej
     * nazwy plików stałyby o kolumnę w lewo od nazw katalogów na tym samym
     * poziomie, a drzewo wyglądałoby jak dwie listy przeplecione.
     */
    public function testLeafAndBranchPrefixesAreEquallyWide(): void
    {
        $leaf = TreeView::prefixOf(new TreeNode('/c', 'c', [], true));
        $branch = TreeView::prefixOf(new TreeNode('/b', 'b', [], true, true));

        self::assertSame(mb_strlen($branch), mb_strlen($leaf));
    }

    public function testIndentedNamesLandFurtherRightWithEveryLevel(): void
    {
        $texts = self::textsOf((new TreeView([
            new TreeNode('/a', 'a', [], false, true, true),
            new TreeNode('/a/b', 'b', [true], true),
        ]))->draw(new Rect(0, 0, 2, 40)));

        self::assertCount(2, $texts);
        self::assertStringEndsWith('a', $texts[0]);
        self::assertStringEndsWith('b', $texts[1]);
        self::assertGreaterThan(
            mb_strlen($texts[0]),
            mb_strlen($texts[1]),
            'wiersz głębszy jest dłuższy dokładnie o swoje wcięcie',
        );
    }

    /** Drzewo wycina okno tak samo jak lista sekcji: `offset` liczy się w wierszach. */
    public function testWindowShowsTheSliceStartingAtTheOffset(): void
    {
        $nodes = [];

        for ($index = 0; $index < 6; ++$index) {
            $nodes[] = new TreeNode('/w' . $index, 'wezel-' . $index, [], true);
        }

        $texts = self::textsOf((new TreeView($nodes, 2))->draw(new Rect(0, 0, 3, 40)));

        self::assertCount(3, $texts);
        self::assertStringEndsWith('wezel-2', $texts[0]);
        self::assertStringEndsWith('wezel-4', $texts[2]);
    }

    /** Kursor podaje się numerem w **pełnej** liście, a podkreśla się w wycinku. */
    public function testCursorOutsideTheWindowUnderlinesNothing(): void
    {
        $nodes = [];

        for ($index = 0; $index < 6; ++$index) {
            $nodes[] = new TreeNode('/w' . $index, 'wezel-' . $index, [], true);
        }

        $inside = (new TreeView($nodes, 2, 3))->draw(new Rect(0, 0, 3, 40));
        $outside = (new TreeView($nodes, 2, 0))->draw(new Rect(0, 0, 3, 40));

        self::assertCount(1, self::highlightsOf($inside));
        self::assertSame([], self::highlightsOf($outside), 'kursor poza oknem nie podkreśla przypadkowego wiersza');
    }

    public function testValueRidesOnTheRightEdgeLikeInAnyListRow(): void
    {
        $primitives = (new TreeView([
            new TreeNode('/a/plik.txt', 'plik.txt', [], true, false, false, '12 kB'),
        ]))->draw(new Rect(0, 0, 1, 40));

        $texts = self::textsOf($primitives);

        self::assertContains('12 kB', $texts);
    }

    /**
     * Drzewo bez limitu głębokości potrafi zepchnąć nazwę poza panel. Wcięcie
     * ustępuje wtedy **od lewej**: znika początek prowadnic, a znacznik gałęzi
     * i nazwa zostają — bo to one niosą treść.
     */
    public function testDeepIndentYieldsFromTheLeftSoTheNameSurvives(): void
    {
        $guides = array_fill(0, 12, true);
        $texts = self::textsOf((new TreeView([
            new TreeNode('/gleboko', 'nazwa', $guides, true),
        ]))->draw(new Rect(0, 0, 1, 20)));

        self::assertCount(1, $texts);
        self::assertStringContainsString('nazwa', $texts[0]);
        self::assertStringStartsWith('…', $texts[0], 'ucięte jest wcięcie, a nie nazwa');
    }

    public function testScrollbarAppearsOnlyWhenThereIsSomethingToScroll(): void
    {
        $nodes = [];

        for ($index = 0; $index < 9; ++$index) {
            $nodes[] = new TreeNode('/w' . $index, 'wezel-' . $index, [], true);
        }

        $withBar = (new TreeView($nodes, 0, 0, new ScrollPosition(0, 3, 9)))->draw(new Rect(0, 0, 3, 40));
        $withoutBar = (new TreeView($nodes, 0, 0))->draw(new Rect(0, 0, 3, 40));

        self::assertCount(1, array_filter($withBar, static fn (Primitive $p): bool => $p instanceof Scrollbar));
        self::assertSame([], array_filter($withoutBar, static fn (Primitive $p): bool => $p instanceof Scrollbar));
    }

    public function testEmptyRectangleDrawsNothingAtAll(): void
    {
        self::assertSame([], (new TreeView([new TreeNode('/a', 'a')]))->draw(new Rect(0, 0, 0, 40)));
    }

    /** Rola wiersza obejmuje **cały** wiersz, wraz z prowadnicami — jedna, nie dwie. */
    public function testRoleOfTheNodeColoursTheWholeRow(): void
    {
        $primitives = (new TreeView([
            new TreeNode('/a', 'katalog/', [true], true, true, false, '', Role::Accent),
        ]))->draw(new Rect(0, 0, 1, 40));

        $runs = array_values(array_filter($primitives, static fn (Primitive $p): bool => $p instanceof TextRun));

        self::assertCount(1, $runs, 'prowadnice i nazwa idą jednym napisem, nie dwoma');
        self::assertSame(Role::Accent, $runs[0]->role);
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<string>
     */
    private static function textsOf(array $primitives): array
    {
        $texts = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $texts[] = $primitive->text;
            }
        }

        return $texts;
    }

    /**
     * @param list<Primitive> $primitives
     *
     * @return list<Primitive> podkłady zaznaczenia, czyli wypełnione prostokąty
     */
    private static function highlightsOf(array $primitives): array
    {
        return array_values(array_filter(
            $primitives,
            static fn (Primitive $p): bool => $p instanceof RoundRect && $p->fill !== null,
        ));
    }
}
