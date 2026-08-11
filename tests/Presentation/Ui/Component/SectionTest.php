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
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\Section;
use LightManager\Presentation\Ui\Component\SectionList;
use PHPUnit\Framework\TestCase;

/**
 * Zwijana sekcja i lista, która ją rysuje (krok 22).
 *
 * Sprawdzian jest w dwóch miejscach naraz, bo w dwóch miejscach mieszka: sekcja
 * mówi, ile zajmie i co pokaże, a lista wycina z tego okno. Rozjazd między nimi —
 * wysokość niezgodna z liczbą wierszy — jest tym błędem, który widać dopiero po
 * przewinięciu, więc pilnuje go osobny test.
 */
final class SectionTest extends TestCase
{
    public function testExpandedSectionCountsItsHeaderContentAndTrailingBlankLine(): void
    {
        $section = new Section('id', 'ROZMIAR', [new ListRow('a'), new ListRow('b')]);

        self::assertSame(4, $section->height());
        self::assertCount(4, $section->lines(), 'wysokość i liczba wierszy nie mają prawa się rozjechać');
    }

    public function testCollapsedSectionIsOneRowRegardlessOfItsContent(): void
    {
        $section = new Section('id', 'ROZMIAR', [new ListRow('a'), new ListRow('b')], true);

        self::assertSame(1, $section->height());
        self::assertCount(1, $section->lines());
    }

    public function testMarkerTellsTheStateApartAndTheLabelFollowsIt(): void
    {
        $open = (new Section('id', 'ROZMIAR', []))->lines()[0];
        $closed = (new Section('id', 'ROZMIAR', [], true))->lines()[0];

        self::assertSame(Section::OPEN . ' ROZMIAR', $open->left);
        self::assertSame(Section::CLOSED . ' ROZMIAR', $closed->left);
        self::assertSame(Role::Accent, $open->role, 'nagłówek rysuje się rolą motywu, nie kolorem');
    }

    public function testEmptySectionStillShowsItsHeader(): void
    {
        self::assertSame(2, (new Section('id', 'PUSTA', []))->height());
        self::assertCount(2, (new Section('id', 'PUSTA', []))->lines());
    }

    public function testRowCountAddsUpEverySectionIncludingCollapsedOnes(): void
    {
        self::assertSame(7, SectionList::rowCount(self::threeSections()));
    }

    public function testRowOfPointsAtTheHeaderOfTheGivenSection(): void
    {
        $sections = self::threeSections();

        self::assertSame(0, SectionList::rowOf($sections, 0));
        self::assertSame(4, SectionList::rowOf($sections, 1), 'druga sekcja zaczyna się za czterema wierszami pierwszej');
        self::assertSame(6, SectionList::rowOf($sections, 2), 'sekcja pusta zabiera nagłówek i odstęp');
    }

    public function testCollapsingASectionMovesEverythingBelowItUp(): void
    {
        $expanded = [new Section('a', 'A', [new ListRow('x')]), new Section('b', 'B', [])];
        $collapsed = [new Section('a', 'A', [new ListRow('x')], true), new Section('b', 'B', [])];

        self::assertSame(3, SectionList::rowOf($expanded, 1));
        self::assertSame(1, SectionList::rowOf($collapsed, 1));
    }

    /** Sekcje przewijają się jak jedna lista — okno tnie w poprzek ich granic. */
    public function testWindowCutsAcrossSectionBoundaries(): void
    {
        $primitives = (new SectionList(self::threeSections(), 2))->draw(new Rect(0, 0, 4, 40));

        self::assertSame(
            ['y', Section::OPEN . ' DRUGA'],
            self::textsOf($primitives),
            'okno zaczyna się w środku pierwszej sekcji i sięga nagłówka drugiej',
        );
    }

    public function testCursorHighlightsTheHeaderOfItsSection(): void
    {
        $primitives = (new SectionList(self::threeSections(), 0, 1))->draw(new Rect(0, 0, 6, 40));
        $highlight = self::firstHighlight($primitives);

        self::assertNotNull($highlight, 'kursor bez podkładu byłby niewidoczny');
        self::assertSame(4, $highlight->bounds->row, 'podkład leży pod nagłówkiem drugiej sekcji');
    }

    public function testCursorScrolledOutOfTheWindowHighlightsNothing(): void
    {
        $primitives = (new SectionList(self::threeSections(), 5, 0))->draw(new Rect(0, 0, 1, 40));

        self::assertNull(
            self::firstHighlight($primitives),
            'numer bezwzględny podkreśliłby w wycinku przypadkowy wiersz',
        );
    }

    public function testScrollbarComesFromTheWindowGivenFromOutside(): void
    {
        $primitives = (new SectionList(self::threeSections(), 0, null, new ScrollPosition(0, 3, 6)))
            ->draw(new Rect(0, 0, 3, 40));

        self::assertNotSame([], array_filter($primitives, static fn (Primitive $p): bool => $p instanceof Scrollbar));
    }

    public function testEmptyRectangleProducesNothing(): void
    {
        self::assertSame([], (new SectionList(self::threeSections()))->draw(new Rect(0, 0, 0, 0)));
    }

    public function testListWithoutSectionsDrawsNothing(): void
    {
        self::assertSame([], (new SectionList([]))->draw(new Rect(0, 0, 5, 40)));
        self::assertSame(0, SectionList::rowCount([]));
    }

    /** @return list<Section> */
    private static function threeSections(): array
    {
        return [
            new Section('a', 'PIERWSZA', [new ListRow('x'), new ListRow('y')]),
            new Section('b', 'DRUGA', []),
            new Section('c', 'TRZECIA', [new ListRow('z')], true),
        ];
    }

    /** @param list<Primitive> $primitives */
    private static function firstHighlight(array $primitives): ?RoundRect
    {
        foreach ($primitives as $primitive) {
            if ($primitive instanceof RoundRect) {
                return $primitive;
            }
        }

        return null;
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
}
