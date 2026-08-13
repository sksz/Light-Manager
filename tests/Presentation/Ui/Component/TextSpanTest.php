<?php

declare(strict_types=1);

namespace LightManager\Tests\Presentation\Ui\Component;

use LightManager\Presentation\Ui\Component\TextSpan;
use PHPUnit\Framework\TestCase;

/**
 * Zakresy dopasowania — krok 30.
 *
 * Cała klasa ma jedną rzecz do udowodnienia i to nie jest szukanie podciągu:
 * **przesunięcie liczy się w znakach, nie w bajtach**. Rozstrzygnięcie zapadło
 * przed pierwszą linią kodu tamtego kroku, a różnicę widać dopiero na alfabecie
 * spoza ASCII — i właśnie tam ten zestaw patrzy najuważniej.
 */
final class TextSpanTest extends TestCase
{
    public function testFindsAMatchAtTheBeginning(): void
    {
        self::assertSame([[0, 3]], self::pairs(TextSpan::occurrencesOf('doc', 'documents.txt')));
    }

    public function testFindsAMatchInTheMiddle(): void
    {
        self::assertSame([[4, 3]], self::pairs(TextSpan::occurrencesOf('men', 'docmments.txt')));
    }

    public function testFindsAMatchAtTheEnd(): void
    {
        self::assertSame([[10, 3]], self::pairs(TextSpan::occurrencesOf('txt', 'notatka-1.txt')));
    }

    public function testFindsEveryOccurrenceInOneName(): void
    {
        self::assertSame([[0, 2], [5, 2], [10, 2]], self::pairs(TextSpan::occurrencesOf('ab', 'ab-x-ab-x-ab')));
    }

    /** Wystąpienia nie zachodzą na siebie: `aa` w `aaaa` to dwa dopasowania, nie trzy. */
    public function testOccurrencesNeverOverlap(): void
    {
        self::assertSame([[0, 2], [2, 2]], self::pairs(TextSpan::occurrencesOf('aa', 'aaaa')));
    }

    public function testNoMatchGivesNoSpans(): void
    {
        self::assertSame([], TextSpan::occurrencesOf('zzz', 'documents.txt'));
    }

    public function testEmptyFragmentMatchesNothing(): void
    {
        self::assertSame([], TextSpan::occurrencesOf('', 'documents.txt'));
    }

    public function testCaseIsIgnored(): void
    {
        self::assertSame([[0, 3]], self::pairs(TextSpan::occurrencesOf('DOC', 'documents.txt')));
    }

    /**
     * Składanie wielkości liter obejmuje alfabety spoza ASCII: `Ł` znajduje `ł`.
     *
     * Bez tego filtr działałby po polsku „prawie”, czyli w sposób najgorszy
     * z możliwych — użytkownik nie wiedziałby, czy pliku nie ma, czy tylko nie
     * został znaleziony.
     */
    public function testCaseIsIgnoredBeyondAscii(): void
    {
        self::assertSame([[0, 4]], self::pairs(TextSpan::occurrencesOf('ŁÓDŹ', 'łódź-2026.txt')));
    }

    /**
     * Przesunięcie liczy się w **znakach**: `ć` w `zażółć` stoi na piątej
     * pozycji, choć bajt jest dziesiąty.
     */
    public function testOffsetIsCountedInCharactersNotBytes(): void
    {
        $spans = TextSpan::occurrencesOf('ć', 'zażółć.txt');

        self::assertSame([[5, 1]], self::pairs($spans));
        self::assertSame(8, strpos('zażółć.txt', 'ć'), 'w bajtach to samo miejsce wypada trzy kolumny dalej');
    }

    public function testClippingKeepsWhatFits(): void
    {
        $clipped = (new TextSpan(2, 6))->clippedTo(5);

        self::assertNotNull($clipped);
        self::assertTrue($clipped->equals(new TextSpan(2, 3)));
    }

    public function testClippingDropsASpanThatStartsBeyondTheText(): void
    {
        self::assertNull((new TextSpan(7, 2))->clippedTo(5));
    }

    public function testClippingLeavesAFittingSpanAlone(): void
    {
        $clipped = (new TextSpan(1, 2))->clippedTo(10);

        self::assertNotNull($clipped);
        self::assertTrue($clipped->equals(new TextSpan(1, 2)));
    }

    /**
     * @param list<TextSpan> $spans
     *
     * @return list<array{int, int}>
     */
    private static function pairs(array $spans): array
    {
        return array_map(static fn (TextSpan $span): array => [$span->offset, $span->length], $spans);
    }
}
