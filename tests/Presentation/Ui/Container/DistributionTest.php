<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Container;

use LightManager\Presentation\Ui\Container\Distribution;
use LightManager\Presentation\Ui\Container\Span;
use PHPUnit\Framework\TestCase;

/**
 * Rozdział miejsca — krok 27.
 *
 * Rachunek nie jest nowy: stał od kroku 18 w `VStack` i pilnowały go tamtejsze
 * testy, przepuszczone przez rysowanie kontenera. Wyprowadzony, daje się wreszcie
 * sprawdzić **wprost** — jednym wywołaniem, bez ani jednego prymitywu. To jest
 * cała różnica między tym zestawem a `VStackTest`, który zostaje na miejscu
 * i pilnuje, że wyprowadzenie niczego nie zmieniło.
 *
 * Miary są bezwymiarowe, więc ten sam wynik obowiązuje wiersze kontenera
 * i kolumny tabeli.
 */
final class DistributionTest extends TestCase
{
    public function testFixedTakeTheirOwnAndFlexibleTakeTheRest(): void
    {
        $sizes = Distribution::of([Span::flexible(), Span::fixed(10), Span::fixed(6)], 40);

        self::assertSame([24, 10, 6], $sizes);
    }

    /** Nadmiar dzieli się po równo, a reszta z dzielenia idzie do pierwszych. */
    public function testSpareIsSharedEvenlyBetweenFlexibleParticipants(): void
    {
        $sizes = Distribution::of([Span::flexible(), Span::flexible(), Span::flexible()], 20);

        self::assertSame([7, 7, 6], $sizes);
    }

    /** Bez uczestnika elastycznego nadmiar zostaje niewykorzystany. */
    public function testNobodyIsStretchedWithoutAsking(): void
    {
        self::assertSame([10, 6], Distribution::of([Span::fixed(10), Span::fixed(6)], 40));
    }

    /**
     * Sedno reguły: uczestnik poniżej swojego minimum **znika w całości**.
     *
     * Kolumna z datą zwężona do czterech znaków pokazuje „202…”, czyli nic —
     * a przy tym zabiera cztery kolumny nazwie, która by je wykorzystała. Stąd
     * `rigid()`: tyle albo nic.
     */
    public function testARigidParticipantDisappearsEntirelyInsteadOfShrinking(): void
    {
        $sizes = Distribution::of([Span::flexible(4), Span::rigid(17, 1)], 18);

        self::assertSame([18, 0], $sizes, 'data ustąpiła w całości, a nazwa wzięła jej miejsce');
    }

    /**
     * Odwrotnie zachowuje się `fixed()` — i **tak ma zostać**, bo tak zachowywał
     * się `Slot` od kroku 18: pas podglądu niższy o wiersz jest nadal pasem
     * podglądu. Różnicę widać dopiero obok siebie i dlatego oba przypadki stoją
     * w sąsiednich testach.
     */
    public function testAFixedParticipantShrinksGraduallyInstead(): void
    {
        $sizes = Distribution::of([Span::flexible(4), Span::fixed(17, 1)], 18);

        self::assertSame([4, 14], $sizes, 'ustąpił trzy miary, a nie wszystkie');
    }

    /**
     * Ustępują po kolei, wedle `yieldOrder`, a nie wszyscy naraz — na układzie
     * kolumn listy plików, bo to on jest prawdziwym odbiorcą tej reguły.
     *
     * Nazwa jest elastyczna z minimum 20; rozmiar, data i prawa są sztywne
     * i ustępują w kolejności odwrotnej do ważności: **prawa, data, rozmiar**.
     */
    public function testParticipantsYieldOneAfterAnotherInOrder(): void
    {
        $columns = [Span::flexible(20), Span::rigid(9, 3), Span::rigid(17, 2), Span::rigid(9, 1)];

        // Szerokie okno: wszystko widać, a nadmiar idzie w całości do nazwy.
        self::assertSame([25, 9, 17, 9], Distribution::of($columns, 60));

        // Za ciasno o kilka kolumn — pierwsze ustępują prawa (yieldOrder 1),
        // a po nich data, bo bez niej też się nie mieści.
        self::assertSame([31, 9, 0, 0], Distribution::of($columns, 40));

        // Jeszcze ciaśniej — zostaje sama nazwa.
        self::assertSame([25, 0, 0, 0], Distribution::of($columns, 25));
        self::assertSame([12, 0, 0, 0], Distribution::of($columns, 12));
    }

    public function testEverythingSurvivesAnEmptyContainer(): void
    {
        self::assertSame([0, 0], Distribution::of([Span::flexible(4), Span::fixed(9)], 0));
    }

    public function testNegativeSizeIsTreatedAsNothing(): void
    {
        self::assertSame([0], Distribution::of([Span::flexible()], -5));
    }

    public function testNoParticipantsGiveNoSizes(): void
    {
        self::assertSame([], Distribution::of([], 40));
    }

    /**
     * Kontener mniejszy od minimum elastycznego oddaje mu wszystko, co ma —
     * i **mniej niż jego minimum**, bo więcej nie istnieje. Minimum jest progiem
     * ustępowania sąsiadów, a nie obietnicą nie do złamania.
     */
    public function testFlexibleTakesWhatIsLeftEvenBelowItsMinimum(): void
    {
        self::assertSame([3, 0], Distribution::of([Span::flexible(4), Span::rigid(9, 1)], 3));
    }
}
