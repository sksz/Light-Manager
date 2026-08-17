<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Ui;

use LightManager\Application\Ui\Corner;
use LightManager\Application\Ui\Frame;
use LightManager\Application\Ui\FrameText;
use LightManager\Application\Ui\Plane;
use LightManager\Application\Ui\Primitive\Bar;
use LightManager\Application\Ui\Primitive\Bitmap;
use LightManager\Application\Ui\Primitive\CornerBrackets;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\RoundRect;
use LightManager\Application\Ui\Primitive\Scrollbar;
use LightManager\Application\Ui\Primitive\TextMark;
use LightManager\Application\Ui\Primitive\TextRun;
use LightManager\Application\Ui\Primitive\Weight;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;
use LightManager\Domain\ValueObject\ScrollPosition;
use PHPUnit\Framework\TestCase;

/**
 * Warstwa tekstowa klatki — **co na niej pisze** (krok 56).
 *
 * Rachunek stał do tego kroku w rendererze tekstowym i był sprawdzany jego
 * testem; przeniósł się tutaj razem z kodem, bo pyta o niego dziś także
 * zaznaczanie treści w dwóch pozostałych torach. Kryterium kroku brzmi:
 * *odczytana z zaznaczenia treść jest tym samym napisem, który widać* — a to
 * jest zdanie o **każdym** kształcie słownika, nie o jednym.
 */
final class FrameTextTest extends TestCase
{
    /**
     * Złożenie warstwy tekstowej z klatki zawierającej **każdy kształt
     * słownika** — siedem prymitywów naraz.
     *
     * Test istnieje po to, żeby nowy kształt nie wszedł do aplikacji bez
     * odpowiedzi na pytanie „co z niego przeczyta zaznaczenie”. Odpowiedź „nic”
     * jest dopuszczalna (suwak, nawias narożny), ale ma być **zapisana**, a nie
     * przypadkowa.
     */
    public function testEveryPrimitiveOfTheDictionaryHasAnAnswer(): void
    {
        $text = self::textOf(
            new TextRun(0, 0, 'plik.txt', Role::Text),
            new TextMark(0, 0, 'pli', Role::Background, Role::Accent),
            new RoundRect(new Rect(1, 0, 3, 6), null, Role::Border, Corner::Soft),
            new Bar(new Rect(4, 7, 1, 1), Role::Border, Weight::Hairline),
            new Bitmap(new Rect(5, 0, 1, 8), '/tmp/obraz.png', 'obraz.png'),
            new Scrollbar(new Rect(1, 9, 3, 1), new ScrollPosition(0, 3, 9)),
            new CornerBrackets(new Rect(1, 0, 3, 6), Role::Accent),
        );

        // Napis i podświetlenie: treść komórek zostaje, zmieniają się role.
        self::assertSame('plik.txt', implode('', array_map(
            static fn (int $column): string => $text->glyph(0, $column),
            range(0, 7),
        )));
        self::assertSame(Role::Background, $text->foreground(0, 0), 'rola pisma z dopasowania');
        self::assertSame(Role::Accent, $text->background(0, 0), 'tło z dopasowania');
        self::assertSame(Role::Text, $text->foreground(0, 4), 'poza dopasowaniem zostaje rola napisu');

        // Obwódka degraduje się do znaków rysunkowych.
        self::assertSame('╭', $text->glyph(1, 0));
        self::assertSame('╯', $text->glyph(3, 5));

        // Pasek włoskowy to jedna kreska, miniatura — sam podpis.
        self::assertSame('│', $text->glyph(4, 7));
        self::assertSame('obraz.png', implode('', array_map(
            static fn (int $column): string => $text->glyph(5, $column),
            range(0, 8),
        )));

        // Suwak i nawias narożny **nie mają odpowiednika** i to jest odpowiedź,
        // a nie przeoczenie: pierwszy zajmuje pół kolumny, drugi jest ozdobą
        // narożnika, którą obwódka już narysowała.
        self::assertSame(' ', $text->glyph(1, 9), 'suwak nie zostawia znaku');
        self::assertSame('╭', $text->glyph(1, 0), 'nawias narożny nie nadpisuje obwódki');
    }

    /** Wypełnienie maluje tło komórek, a znaku nie rusza. */
    public function testFillPaintsTheGroundAndLeavesTheGlyphs(): void
    {
        $text = self::textOf(
            new TextRun(0, 0, 'abc', Role::Text),
            new Bar(new Rect(0, 0, 1, 3), Role::Selection, Weight::Fill),
        );

        self::assertSame('a', $text->glyph(0, 0));
        self::assertSame(Role::Selection, $text->background(0, 0));
        self::assertSame(Role::Text, $text->foreground(0, 0));
    }

    /**
     * Płaszczyzna nieprzezroczysta **wymazuje** to, co pod nią — inaczej okno
     * nakładane przepuszczałoby treść ekranu, a zaznaczenie oddawałoby dwa
     * napisy naraz.
     */
    public function testAnOpaquePlaneHidesWhatIsUnderIt(): void
    {
        $frame = new Frame([
            new Plane('content', new Rect(0, 0, 1, 10), [new TextRun(0, 0, 'plik.txt', Role::Text)]),
            new Plane('overlay', new Rect(0, 2, 1, 4), [new TextRun(0, 2, 'okno', Role::Text)], opaque: true),
        ]);

        // Wiersz to „pl” spod okna, treść okna i „xt”, które okno odsłania —
        // czyli dokładnie to, co widać.
        self::assertSame(['ploknoxt'], FrameText::of($frame, 1, 10)->textIn(new Rect(0, 0, 1, 10)));
    }

    /**
     * Napis w prostokącie: **spacje z prawej lecą, z lewej zostają**.
     *
     * Prawe są wyrównaniem klatki i nie niosą nic; lewe są kształtem bloku —
     * wcięciem drzewa, wyrównaniem kolumny — czyli tym, co użytkownik obrysował.
     */
    public function testReadingARectangleTrimsOnlyTheTrailingSpaces(): void
    {
        $text = self::textOf(
            new TextRun(0, 3, 'plik.txt', Role::Text),
            new TextRun(1, 3, 'inny.txt', Role::Text),
        );

        self::assertSame(['   plik.txt', '   inny.txt'], $text->textIn(new Rect(0, 0, 2, 20)));
    }

    /** Prostokąt sięgający poza siatkę czyta tyle, ile na niej leży. */
    public function testReadingBeyondTheGridStopsAtItsEdge(): void
    {
        $text = self::textOf(new TextRun(0, 0, 'abc', Role::Text));
        $lines = $text->textIn(new Rect(0, 0, 40, 40));

        self::assertCount(8, $lines, 'tyle wierszy, ile ma siatka — nie tyle, ile poproszono');
        self::assertSame('abc', $lines[0]);
        self::assertSame([], $text->textIn(new Rect(0, 0, 0, 0)), 'pusty prostokąt nie ma wierszy');
    }

    /** Prymityw poza siatką nie wywraca rachunku — jak każdy inny. */
    public function testAPrimitiveOutsideTheGridIsIgnored(): void
    {
        $text = self::textOf(new TextMark(9, 9, 'pli', Role::Background, Role::Accent));

        self::assertSame([''], $text->textIn(new Rect(0, 0, 1, 4)));
    }

    private static function textOf(Primitive ...$primitives): FrameText
    {
        return FrameText::of(
            new Frame([new Plane('content', new Rect(0, 0, 8, 12), array_values($primitives))]),
            8,
            12,
        );
    }
}
