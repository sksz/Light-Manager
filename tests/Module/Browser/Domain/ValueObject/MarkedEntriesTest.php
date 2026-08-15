<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\Browser\Domain\ValueObject;

use LightManager\Module\Browser\Domain\Exception\InvalidEntryException;
use LightManager\Module\Browser\Domain\ValueObject\MarkedEntries;
use PHPUnit\Framework\TestCase;

/**
 * Zbiór zaznaczonych wpisów — reguła zbioru bez ani jednego wywołania
 * systemowego (krok 43).
 *
 * Test pilnuje trzech rzeczy, na których stoi cały krok: **nazwa jest
 * tożsamością** (nie numer), **katalog waży `null`, a nie zero** i **zbiór
 * przycięty po zmianie na dysku zostawia to, czego zmiana nie dotknęła**.
 */
final class MarkedEntriesTest extends TestCase
{
    public function testAnEmptySetCountsNothingAndWeighsNothing(): void
    {
        $marked = MarkedEntries::none();

        self::assertTrue($marked->isEmpty());
        self::assertSame(0, $marked->count());
        self::assertSame(0, $marked->bytes());
        self::assertSame([], $marked->names());
    }

    public function testTogglingAddsAndThenRemovesTheSameEntry(): void
    {
        $marked = MarkedEntries::none()->toggled('raport.pdf', 2048);

        self::assertTrue($marked->has('raport.pdf'));
        self::assertSame(1, $marked->count());
        self::assertSame(2048, $marked->bytes());

        $marked = $marked->toggled('raport.pdf', 2048);

        self::assertFalse($marked->has('raport.pdf'));
        self::assertTrue($marked->isEmpty());
    }

    /**
     * Katalog wolno zaznaczyć (rozstrzygnięcie 7), ale suma go pomija — i osobna
     * liczba mówi wołającemu, że ma się z tego wytłumaczyć w napisie.
     */
    public function testDirectoriesJoinTheSetWithoutJoiningTheSum(): void
    {
        $marked = MarkedEntries::none()
            ->toggled('raport.pdf', 2048)
            ->toggled('dokumenty', null)
            ->toggled('zdjęcia', null);

        self::assertSame(3, $marked->count());
        self::assertSame(2048, $marked->bytes(), 'katalogi ważą zero, bo ich rozmiaru nikt nie zna');
        self::assertSame(2, $marked->directories());
    }

    /** Kolejność jest kolejnością zaznaczania — tą samą, w której pójdzie operacja. */
    public function testNamesKeepTheOrderInWhichTheyWereMarked(): void
    {
        $marked = MarkedEntries::none()
            ->toggled('trzeci', 1)
            ->toggled('pierwszy', 1)
            ->toggled('drugi', 1);

        self::assertSame(['trzeci', 'pierwszy', 'drugi'], $marked->names());
    }

    /**
     * Odwrócenie dotyczy **podanej listy** i tylko jej: wpisy spoza niej zostają
     * w swoim stanie, bo `*` przy włączonym filtrze nie ma prawa ruszyć tego,
     * czego nie widać (rozstrzygnięcie 8).
     */
    public function testInvertingTouchesOnlyTheEntriesGiven(): void
    {
        $marked = MarkedEntries::none()
            ->toggled('schowany', 10)
            ->toggled('widoczny-a', 20);

        $marked = $marked->invertedOn(['widoczny-a' => 20, 'widoczny-b' => 30]);

        self::assertTrue($marked->has('schowany'), 'wpis spoza listy zostaje zaznaczony');
        self::assertFalse($marked->has('widoczny-a'), 'zaznaczony i widoczny — odznaczony');
        self::assertTrue($marked->has('widoczny-b'), 'niezaznaczony i widoczny — zaznaczony');
        self::assertSame(40, $marked->bytes());
    }

    /**
     * Po zmianie na dysku zbiór zostawia to, czego zmiana nie dotknęła — i to
     * jest jedyna droga, którą użytkownik dowie się, co się nie udało.
     */
    public function testPruningKeepsWhatSurvivedAndRefreshesItsSize(): void
    {
        $marked = MarkedEntries::none()
            ->toggled('usunięty.txt', 100)
            ->toggled('pominięty.txt', 200);

        $marked = $marked->keptFrom(['pominięty.txt' => 250, 'obcy.txt' => 999]);

        self::assertSame(['pominięty.txt'], $marked->names());
        self::assertSame(250, $marked->bytes(), 'rozmiar bierze się ze świeżego odczytu');
        self::assertFalse($marked->has('obcy.txt'), 'przycinanie nie zaznacza niczego nowego');
    }

    /**
     * Nazwa wyglądająca jak liczba jest pułapką PHP-a, a nie ciekawostką: klucz
     * tablicy sprowadza się wtedy do `int`-a, więc każde przeglądanie kluczy
     * musi to przewidzieć.
     */
    public function testANumericNameSurvivesBeingATableKey(): void
    {
        $marked = MarkedEntries::none()->toggled('2026', 42);

        self::assertTrue($marked->has('2026'));
        self::assertSame(['2026'], $marked->names());
        self::assertSame(42, $marked->bytes());
    }

    public function testAnEmptyNameIsRejected(): void
    {
        $this->expectException(InvalidEntryException::class);

        new MarkedEntries(['' => 1]);
    }

    public function testAPathInsteadOfANameIsRejected(): void
    {
        $this->expectException(InvalidEntryException::class);

        new MarkedEntries(['katalog/plik' => 1]);
    }

    public function testANegativeSizeIsRejected(): void
    {
        $this->expectException(InvalidEntryException::class);

        new MarkedEntries(['plik' => -1]);
    }

    public function testTwoSetsOfTheSameEntriesAreEqual(): void
    {
        $first = MarkedEntries::none()->toggled('a', 1);
        $second = MarkedEntries::none()->toggled('a', 1);

        self::assertTrue($first->equals($second));
        self::assertFalse($first->equals($second->toggled('b', 2)));
    }
}
