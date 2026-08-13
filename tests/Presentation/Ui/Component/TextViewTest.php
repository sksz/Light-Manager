<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Component;

use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\ScrollPosition;
use LightManager\Presentation\Ui\Component\TextView;
use PHPUnit\Framework\TestCase;

/**
 * Widok tekstu: zawijanie, przycinanie, numery i suwak (krok 29).
 *
 * Komponent dostaje gotowe wiersze i cała jego treść to **dwie reguły**:
 * zawijamy po znaku, a wiersz, który nie zmieściłby się nawet zawinięty,
 * zostaje w jednym wierszu przycięty. Reszta testów pilnuje, żeby przy tym
 * niczego nie zgubić — pustej linijki, numeracji ani kolumny na suwak.
 */
final class TextViewTest extends TestCase
{
    public function testShortLinesGoOnePerRow(): void
    {
        $texts = self::textsOf((new TextView(['alfa', 'beta', 'gamma']))->draw(new Rect(0, 0, 5, 20)));

        self::assertSame(['alfa', 'beta', 'gamma'], $texts);
    }

    public function testLongLineWrapsByCharacter(): void
    {
        $view = new TextView(['abcdefghij']);

        self::assertSame(['abcd', 'efgh', 'ij'], self::textsOf($view->draw(new Rect(0, 0, 5, 4))));
    }

    /** Zawijanie łamie po znaku, a nie po słowie — wcięcie ma zostać wcięciem. */
    public function testWrappingKeepsIndentationInsteadOfBreakingOnSpaces(): void
    {
        $view = new TextView(['    return $a;']);

        self::assertSame(['    ret', 'urn $a;'], self::textsOf($view->draw(new Rect(0, 0, 4, 7))));
    }

    public function testWrappingOffTrimsEveryLineToOneRow(): void
    {
        $view = new TextView(['abcdefghij', 'kl'], wrap: false);

        self::assertSame(['abc…', 'kl'], self::textsOf($view->draw(new Rect(0, 0, 5, 4))));
    }

    /**
     * Wiersz dłuższy niż **cały** prostokąt zawija się i wypełnia panel.
     *
     * Do poprawki z 2026-08-12 test twierdził coś odwrotnego: że taki wiersz
     * zostaje przycięty do jednej linijki, żeby „nie zamienić panelu w linijkę
     * rozciągniętą na tysiące wierszy siatki”. Obawa była nieuzasadniona —
     * rysowanie kończy się na dolnej krawędzi prostokąta — a jej skutkiem było,
     * że **jedyne wiersze, które nigdy się nie zawijały, to te najdłuższe**.
     * Plik o jednej długiej linii pokazywał jedną linijkę i pusty panel.
     */
    public function testLineLongerThanTheWholeRectangleFillsThePanel(): void
    {
        $monstrous = str_repeat('x', 4 * 5 + 1);
        $view = new TextView([$monstrous, 'po']);

        $texts = self::textsOf($view->draw(new Rect(0, 0, 5, 4)));

        self::assertSame(['xxxx', 'xxxx', 'xxxx', 'xxxx', 'xxxx'], $texts, 'panel wypełniony po brzegi');
    }

    /** Za dolną krawędzią nic już nie powstaje — także z wiersza, który się nie skończył. */
    public function testNothingIsBuiltBelowTheBottomEdge(): void
    {
        $monstrous = str_repeat('x', 10_000);

        self::assertCount(3, self::textsOf((new TextView([$monstrous]))->draw(new Rect(0, 0, 3, 4))));
    }

    /**
     * Ten sam wiersz zawija się tak samo, gdziekolwiek wypadł przy przewijaniu.
     *
     * Wysokość prostokąta jest **sufitem liczby kawałków, a nie progiem
     * zawijania**, więc wiersz stojący tuż nad dolną krawędzią zawija się
     * dokładnie tak, jak ten na górze — tyle że widać z niego mniej.
     */
    public function testWrappingDoesNotDependOnWhereTheLineLands(): void
    {
        $line = str_repeat('x', 8);
        $atTop = self::textsOf((new TextView([$line]))->draw(new Rect(0, 0, 5, 4)));
        $atBottom = self::textsOf((new TextView(['a', 'b', 'c', 'd', $line]))->draw(new Rect(0, 0, 5, 4)));

        self::assertSame(['xxxx', 'xxxx'], $atTop);
        self::assertSame(['a', 'b', 'c', 'd', 'xxxx'], $atBottom, 'zawinięcie ucina prostokąt, nie regułę');
    }

    public function testEmptyLineKeepsItsRow(): void
    {
        $texts = self::textsOf((new TextView(['alfa', '', 'beta']))->draw(new Rect(0, 0, 5, 10)));
        $rows = self::rowsOf((new TextView(['alfa', '', 'beta']))->draw(new Rect(0, 0, 5, 10)));

        self::assertSame(['alfa', 'beta'], $texts, 'pusty wiersz nie rysuje napisu');
        self::assertSame([0, 2], $rows, 'ale zajmuje swój wiersz — numeracja ma się zgadzać z plikiem');
    }

    public function testContentBeyondTheRectangleIsNotDrawn(): void
    {
        $view = new TextView(['a', 'b', 'c', 'd']);

        self::assertSame(['a', 'b'], self::textsOf($view->draw(new Rect(0, 0, 2, 10))));
    }

    public function testEmptyRectangleDrawsNothing(): void
    {
        self::assertSame([], (new TextView(['alfa']))->draw(new Rect(0, 0, 0, 10)));
        self::assertSame([], (new TextView(['alfa']))->draw(new Rect(0, 0, 5, 0)));
    }

    public function testScrollbarTakesItsOwnColumnInsteadOfCoveringTheText(): void
    {
        $view = new TextView(['abcdefghij'], true, new ScrollPosition(0, 10, 100));
        $primitives = $view->draw(new Rect(0, 0, 5, 5));

        self::assertSame(['abcd', 'efgh', 'ij'], self::textsOf($primitives), 'treść zwęziła się o kolumnę suwaka');
        self::assertInstanceOf(Scrollbar::class, end($primitives));
    }

    public function testScrollbarIsAbsentWhenEverythingFits(): void
    {
        $view = new TextView(['alfa'], true, new ScrollPosition(0, 100, 100));

        foreach ($view->draw(new Rect(0, 0, 5, 10)) as $primitive) {
            self::assertNotInstanceOf(Scrollbar::class, $primitive);
        }
    }

    public function testLineNumbersCountFromTheGivenFirstNumber(): void
    {
        $view = new TextView(['alfa', 'beta'], true, null, 41);

        self::assertSame(['41', 'alfa', '42', 'beta'], self::textsOf($view->draw(new Rect(0, 0, 5, 20))));
    }

    /**
     * Wiersz zawinięty ma numer **tylko przy pierwszym kawałku** — jak w edytorach.
     *
     * Prostokąt jest szerszy niż przed poprawką z 2026-08-12, bo kolumna numerów
     * liczy się odtąd z **wysokości** prostokąta, a nie z liczby wczytanych
     * wierszy: w dziesięciu kolumnach numery ustąpiłyby dziś treści w całości,
     * i słusznie — dwucyfrowy numer zabierałby jej trzy znaki z dziesięciu.
     */
    public function testWrappedLineIsNumberedOnce(): void
    {
        $view = new TextView([str_repeat('a', 20), 'k'], true, null, 7);

        self::assertSame(
            ['7', str_repeat('a', 17), 'aaa', '8', 'k'],
            self::textsOf($view->draw(new Rect(0, 0, 5, 20))),
        );
    }

    public function testLineNumbersUseTheMutedRole(): void
    {
        $runs = [];

        foreach ((new TextView(['alfa'], true, null, 1))->draw(new Rect(0, 0, 5, 20)) as $primitive) {
            if ($primitive instanceof TextRun) {
                $runs[] = $primitive;
            }
        }

        self::assertCount(2, $runs, 'numer i treść — dwa napisy w wierszu');
        self::assertSame('1', $runs[0]->text);
        self::assertSame(Role::Muted, $runs[0]->role, 'numer stoi z boku i ma się nie narzucać');
        self::assertSame('alfa', $runs[1]->text);
        self::assertSame(Role::Text, $runs[1]->role);
    }

    /** Numery ustępują treści: w wąskim panelu kolumna numerów nie powstaje. */
    public function testNumbersYieldToContentWhenThePanelIsNarrow(): void
    {
        $view = new TextView(['alfa'], true, null, 1000);

        self::assertSame(['alfa'], self::textsOf($view->draw(new Rect(0, 0, 5, 9))));
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
     * @return list<int>
     */
    private static function rowsOf(array $primitives): array
    {
        $rows = [];

        foreach ($primitives as $primitive) {
            if ($primitive instanceof TextRun) {
                $rows[] = $primitive->row;
            }
        }

        return $rows;
    }
}
