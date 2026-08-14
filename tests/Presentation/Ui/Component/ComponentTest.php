<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Component;

use LightManager\Application\Dto\Key;
use LightManager\Application\Dto\KeyPress;
use LightManager\Application\Ui\Corner;
use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Bitmap;
use LightManager\Application\Ui\Primitive\CornerBrackets;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Primitive\Weight;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Presentation\Ui\Component\Button;
use LightManager\Presentation\Ui\Component\Choice;
use LightManager\Presentation\Ui\Component\Dialog;
use LightManager\Presentation\Ui\Component\ImageBox;
use LightManager\Presentation\Ui\Component\Label;
use LightManager\Presentation\Ui\Component\ListRow;
use LightManager\Presentation\Ui\Component\ListView;
use LightManager\Presentation\Ui\Component\Panel;
use LightManager\Presentation\Ui\Component\Spacer;
use LightManager\Presentation\Ui\Component\StatusBar;
use LightManager\Presentation\Ui\Component\Tabs;
use LightManager\Presentation\Ui\Component\Toggle;
use LightManager\Presentation\Ui\Hint;
use LightManager\Presentation\Ui\StatusHints;
use PHPUnit\Framework\TestCase;

final class ComponentTest extends TestCase
{
    public function testLabelPutsLeftTextAtTheStartAndRightTextAtTheEnd(): void
    {
        $primitives = (new Label('nazwa', '12 kB'))->draw(new Rect(3, 2, 1, 20));

        self::assertCount(2, $primitives);
        self::assertInstanceOf(TextRun::class, $primitives[0]);
        self::assertInstanceOf(TextRun::class, $primitives[1]);
        self::assertSame([3, 2, 'nazwa'], [$primitives[0]->row, $primitives[0]->column, $primitives[0]->text]);
        self::assertSame([3, 17, '12 kB'], [$primitives[1]->row, $primitives[1]->column, $primitives[1]->text]);
    }

    public function testLabelTrimsTextThatDoesNotFitAndMarksItWithEllipsis(): void
    {
        $primitives = (new Label('bardzo-dluga-nazwa-pliku.txt'))->draw(new Rect(0, 0, 1, 10));

        self::assertInstanceOf(TextRun::class, $primitives[0]);
        self::assertSame('bardzo-dl…', $primitives[0]->text);
        self::assertSame(10, mb_strlen($primitives[0]->text), 'przycięty napis wypełnia szerokość co do znaku');
    }

    /**
     * **Wartość po prawej też się przycina.**
     *
     * Do poprawki z 2026-08-12 przycinała się wyłącznie treść po lewej, a wartość
     * szła na płótno w całości: opis od polecenia `file` dla zdjęcia — sto
     * dwadzieścia osiem znaków — rysował się w czterdziestokolumnowym panelu jako
     * napis kończący się osiemdziesiąt osiem kolumn za jego krawędzią, czyli po
     * sąsiednim panelu.
     */
    public function testLabelTrimsTheValueSoItNeverLeavesTheRectangle(): void
    {
        $bounds = new Rect(5, 2, 1, 40);
        $primitives = (new Label('Zawartość', str_repeat('x', 128)))->draw($bounds);

        foreach ($primitives as $primitive) {
            self::assertInstanceOf(TextRun::class, $primitive);
            self::assertLessThanOrEqual(
                $bounds->right(),
                $primitive->column + mb_strlen($primitive->text) - 1,
                'napis wychodzi poza prostokąt',
            );
            self::assertGreaterThanOrEqual($bounds->column, $primitive->column);
        }
    }

    /** Wartość mieszcząca się w prostokącie zostaje nietknięta — przycina się tylko za długa. */
    public function testLabelLeavesAFittingValueAlone(): void
    {
        $primitives = (new Label('nazwa', '12 kB'))->draw(new Rect(0, 0, 1, 20));

        self::assertInstanceOf(TextRun::class, $primitives[1]);
        self::assertSame('12 kB', $primitives[1]->text);
    }

    public function testEmptyRectangleProducesNothing(): void
    {
        $bounds = new Rect(0, 0, 0, 0);

        self::assertSame([], (new Label('cokolwiek'))->draw($bounds));
        self::assertSame([], (new ListView([new ListRow('a')]))->draw($bounds));
        self::assertSame([], (new Panel('PATH'))->draw($bounds));
        self::assertSame([], (new Tabs(['a', 'b'], 0))->draw($bounds));
        self::assertSame([], (new StatusBar('x', Role::Info, self::hints('y')))->draw($bounds));
        self::assertSame([], (new ImageBox(null, 'brak'))->draw($bounds));
        self::assertSame([], (new Spacer())->draw(new Rect(0, 0, 5, 5)));
    }

    public function testPanelDrawsBorderBracketsAndLabelInsetFromTheWindowEdge(): void
    {
        $primitives = (new Panel('FILES'))->draw(new Rect(0, 0, 5, 40));

        self::assertInstanceOf(RoundRect::class, $primitives[0]);
        self::assertInstanceOf(CornerBrackets::class, $primitives[1]);
        self::assertInstanceOf(TextRun::class, $primitives[2]);

        self::assertSame([0, 1, 5, 38], [
            $primitives[0]->bounds->row,
            $primitives[0]->bounds->column,
            $primitives[0]->bounds->rows,
            $primitives[0]->bounds->columns,
        ], 'obwódka zostawia po kolumnie oddechu z każdej strony');
        self::assertSame('FILES', $primitives[2]->text);
        self::assertSame(Role::Background, $primitives[2]->clearBehind, 'etykieta wycina sobie miejsce w obwódce');
    }

    public function testPanelWithoutRoomForABorderDrawsNothing(): void
    {
        self::assertSame([], (new Panel('FILES'))->draw(new Rect(0, 0, 2, 40)));
    }

    public function testPanelInnerRectangleLeavesRoomForBorderAndBreathing(): void
    {
        $inner = Panel::inner(new Rect(4, 0, 6, 40));

        self::assertSame([5, 2, 4, 36], [$inner->row, $inner->column, $inner->rows, $inner->columns]);
    }

    public function testListViewMarksTheSelectedRowWithABarAndAnAccentEdge(): void
    {
        $rows = [new ListRow('a'), new ListRow('b'), new ListRow('c')];
        $primitives = (new ListView($rows, 1))->draw(new Rect(0, 2, 3, 20));

        $shapes = array_values(array_filter(
            $primitives,
            static fn ($primitive): bool => $primitive instanceof RoundRect || $primitive instanceof Bar,
        ));

        self::assertCount(2, $shapes);
        self::assertInstanceOf(RoundRect::class, $shapes[0]);
        self::assertSame(Role::Selection, $shapes[0]->fill);
        self::assertSame(Corner::Soft, $shapes[0]->corner);
        self::assertInstanceOf(Bar::class, $shapes[1]);
        self::assertSame(Weight::Edge, $shapes[1]->weight);
        self::assertSame(1, $shapes[0]->bounds->row, 'pasek leży pod drugim wierszem');
    }

    public function testSelectedRowTextSwitchesToTheSelectionColour(): void
    {
        $primitives = (new ListView([new ListRow('a', '', Role::Accent)], 0))->draw(new Rect(0, 0, 1, 10));
        $texts = array_values(array_filter($primitives, static fn ($p): bool => $p instanceof TextRun));

        self::assertInstanceOf(TextRun::class, $texts[0]);
        self::assertSame(Role::SelectionText, $texts[0]->role, 'zaznaczenie przykrywa kolor katalogu');
    }

    public function testListViewDrawsScrollbarOnlyWhenTheListDoesNotFit(): void
    {
        $rows = [new ListRow('a'), new ListRow('b')];

        $fits = (new ListView($rows, null, new ScrollPosition(0, 2, 2)))->draw(new Rect(0, 2, 2, 20));
        $overflows = (new ListView($rows, null, new ScrollPosition(0, 2, 9)))->draw(new Rect(0, 2, 2, 20));

        self::assertSame([], array_filter($fits, static fn ($p): bool => $p instanceof Scrollbar));
        self::assertCount(1, array_filter($overflows, static fn ($p): bool => $p instanceof Scrollbar));
    }

    public function testListViewDrawsNoMoreRowsThanTheRectangleHolds(): void
    {
        $rows = [new ListRow('a'), new ListRow('b'), new ListRow('c'), new ListRow('d')];
        $primitives = (new ListView($rows))->draw(new Rect(0, 0, 2, 10));

        self::assertCount(2, $primitives);
    }

    public function testTabsHighlightTheActiveOneWithoutMovingTheOthers(): void
    {
        $first = (new Tabs(['Wygląd', 'Grafika'], 0))->draw(new Rect(0, 0, 1, 40));
        $second = (new Tabs(['Wygląd', 'Grafika'], 1))->draw(new Rect(0, 0, 1, 40));

        self::assertInstanceOf(TextRun::class, $first[1]);
        self::assertInstanceOf(TextRun::class, $second[1]);
        self::assertSame(
            $first[1]->column,
            $second[1]->column,
            'zmiana zakładki nie przesuwa pozostałych etykiet',
        );
        self::assertInstanceOf(TextRun::class, $first[0]);
        self::assertInstanceOf(TextRun::class, $second[0]);
        self::assertSame(Role::Accent, $first[0]->role);
        self::assertSame(Role::Muted, $second[0]->role);
    }

    public function testTabsStopBeforeRunningOutOfColumns(): void
    {
        $primitives = (new Tabs(['pierwsza', 'druga', 'trzecia'], 0))->draw(new Rect(0, 0, 1, 12));

        self::assertCount(1, $primitives);
    }

    public function testChoiceAndToggleShareTheSameShape(): void
    {
        $choice = (new Choice('Motyw', 'Grafit'))->draw(new Rect(0, 0, 1, 30));
        $toggle = (new Toggle('Wpisy ukryte', true, 'tak', 'nie'))->draw(new Rect(0, 0, 1, 30));

        self::assertCount(2, $choice);
        self::assertCount(2, $toggle);
        self::assertInstanceOf(TextRun::class, $toggle[1]);
        self::assertSame('tak', $toggle[1]->text);
    }

    public function testStatusBarDropsHintsWhenTheMessageLeavesNoRoom(): void
    {
        $roomy = (new StatusBar('błąd', Role::Danger, self::hints('F10 wyjście')))->draw(new Rect(0, 0, 1, 40));
        $tight = (new StatusBar('bardzo długi komunikat o błędzie', Role::Danger, self::hints('F10 wyjście')))
            ->draw(new Rect(0, 0, 1, 34));

        self::assertCount(3, $roomy, 'komunikat, podpowiedzi i przegroda');
        self::assertCount(1, $tight, 'w ciasnym oknie zostaje sam komunikat');
    }

    public function testStatusBarSeparatesHintsWithAHairline(): void
    {
        $primitives = (new StatusBar('', Role::Info, self::hints('F1 pomoc')))->draw(new Rect(0, 0, 1, 40));
        $bars = array_values(array_filter($primitives, static fn ($p): bool => $p instanceof Bar));

        self::assertCount(1, $bars);
        self::assertInstanceOf(Bar::class, $bars[0]);
        self::assertSame(Weight::Hairline, $bars[0]->weight);
    }

    public function testImageBoxTakesAThirdOfTheStripAndCarriesItsOwnCaption(): void
    {
        $primitives = (new ImageBox('/tmp/a.png', '800 × 600'))->draw(new Rect(2, 2, 6, 60));

        self::assertInstanceOf(Bitmap::class, $primitives[0]);
        self::assertSame(20, $primitives[0]->bounds->columns);
        self::assertSame('800 × 600', $primitives[0]->caption);
        self::assertSame('/tmp/a.png', $primitives[0]->path);
    }

    public function testDialogAsksForRoomThatFitsItsWidestLine(): void
    {
        $size = (new Dialog('plik.txt', ['pierwszy wiersz', 'drugi']))->size();

        self::assertSame(5, $size->rows, 'dwa wiersze treści plus obwódka, tytuł i odstęp');
        self::assertSame(19, $size->columns);
    }

    public function testDialogDrawsFrameTitleAndLines(): void
    {
        $primitives = (new Dialog('plik.txt', ['opis']))->draw(new Rect(0, 0, 4, 20));

        self::assertInstanceOf(RoundRect::class, $primitives[0]);
        self::assertSame(Role::Surface, $primitives[0]->fill);
        self::assertInstanceOf(CornerBrackets::class, $primitives[1]);
        self::assertInstanceOf(TextRun::class, $primitives[2]);
        self::assertSame('plik.txt', $primitives[2]->text);
        self::assertInstanceOf(TextRun::class, $primitives[3]);
        self::assertSame('opis', $primitives[3]->text);
    }

    public function testDialogDropsLinesThatWouldLandOnItsBottomBorder(): void
    {
        $primitives = (new Dialog('tytuł', ['a', 'b', 'c']))->draw(new Rect(0, 0, 4, 20));
        $texts = array_values(array_filter($primitives, static fn ($p): bool => $p instanceof TextRun));

        self::assertCount(2, $texts, 'tytuł i jeden wiersz — reszta nie ma gdzie stanąć');
    }

    public function testButtonRunsItsActionOnlyWhenItHasTheCursor(): void
    {
        $ran = 0;
        $action = function () use (&$ran): void {
            ++$ran;
        };

        $idle = new Button('Przywróć', $action, 'help.key.restore');
        $focused = new Button('Przywróć', $action, 'help.key.restore', selected: true);

        self::assertFalse($idle->handle(KeyPress::special(Key::Enter, "\r")));
        self::assertSame(0, $ran);

        self::assertTrue($focused->handle(KeyPress::special(Key::Enter, "\r")));
        self::assertSame(1, $ran);

        self::assertFalse($focused->handle(KeyPress::special(Key::ArrowDown, "\e[B")));
        self::assertSame(1, $ran, 'przycisk reaguje tylko na Enter');
    }

    public function testButtonDeclaresTheKeyItHandles(): void
    {
        $bindings = (new Button('Przywróć', static fn () => null, 'help.key.restore'))->bindings();

        self::assertCount(1, $bindings);
        self::assertSame('Enter', $bindings[0]->display());
        self::assertSame('help.key.restore', $bindings[0]->descriptionKey);
    }

    /** Gotowa pozycja stopki — testy komponentu nie mają czym złożyć jej z wiązań. */
    private static function hints(string $text): StatusHints
    {
        return new StatusHints([new Hint($text)]);
    }
}
