<?php

declare(strict_types=1);

namespace LightManager\Tests\Module\FileInfo;

use LightManager\Module\FileInfo\Application\Dto\TextAnchor;
use LightManager\Module\FileInfo\Infrastructure\TextPreviewService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Odczyt podglądu tekstu (krok 29) — czyli cała trudność tego kroku.
 *
 * Testy pilnują czterech rzeczy, z których każda źle odpowiedziana psuje klatkę
 * albo ją zatrzymuje: **ile czytamy** (tyle, ile widać, niezależnie od rozmiaru
 * pliku), **czym jest wiersz** (bajty i kotwica, w obie strony), **co robimy
 * z bajtem, którego nie da się zdekodować** i **czy plik binarny w ogóle tu
 * trafia**.
 *
 * Pliki powstają w katalogu tymczasowym, bo usługa czyta z dysku i to jest jej
 * jedyne zadanie — atrapa systemu plików sprawdzałaby atrapę.
 */
final class TextPreviewTest extends TestCase
{
    use ResetsSingletons;

    private string $directory;

    private TextPreviewService $texts;

    protected function setUp(): void
    {
        // Usługa pamięta rozpoznanie jednego pliku, a każdy test pisze własne —
        // bez zerowania drugi test dostawałby werdykt pierwszego.
        $this->resetSingleton(TextPreviewService::class);

        $directory = sys_get_temp_dir() . '/lm-text-' . bin2hex(random_bytes(6));
        mkdir($directory);
        $this->directory = $directory;
        $this->texts = TextPreviewService::getInstance();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
        $this->resetSingleton(TextPreviewService::class);
    }

    public function testHeadOfTheFileFillsTheWindowAndPointsAtTheNextOne(): void
    {
        $path = $this->write('lista.txt', "alfa\nbeta\ngamma\ndelta\n");

        $window = $this->texts->forward($path, new TextAnchor(), 2, 40);

        self::assertSame(['alfa', 'beta'], $window->lines);
        self::assertSame(10, $window->next->byte, 'kolejne okno zaczyna się tam, gdzie skończyło się to');
        self::assertSame(3, $window->next->line);
        self::assertSame(22, $window->fileBytes);
    }

    public function testScrollingDownAndBackReturnsToTheSamePlace(): void
    {
        $path = $this->write('lista.txt', "alfa\nbeta\ngamma\ndelta\nepsilon\n");

        $second = $this->texts->forward($path, new TextAnchor(), 2, 40)->next;
        $window = $this->texts->forward($path, $second, 2, 40);

        self::assertSame(['gamma', 'delta'], $window->lines);
        self::assertSame(3, $window->anchor->line);

        $back = $this->texts->backward($path, $second, 2, 40);

        self::assertSame(0, $back->byte, 'powrót o dwa wiersze wraca na początek pliku');
        self::assertSame(1, $back->line);
        self::assertSame(['alfa', 'beta'], $this->texts->forward($path, $back, 2, 40)->lines);
    }

    public function testScrollingUpFromTheStartStaysAtTheStart(): void
    {
        $path = $this->write('lista.txt', "alfa\nbeta\n");

        $anchor = $this->texts->backward($path, new TextAnchor(), 5, 40);

        self::assertSame(0, $anchor->byte);
        self::assertSame(1, $anchor->line);
    }

    public function testTrailingNewlineDoesNotAddAnEmptyLine(): void
    {
        $path = $this->write('lista.txt', "alfa\nbeta\n");

        self::assertSame(['alfa', 'beta'], $this->texts->forward($path, new TextAnchor(), 10, 40)->lines);
    }

    public function testFileWithoutATrailingNewlineKeepsItsLastLine(): void
    {
        $path = $this->write('lista.txt', "alfa\nbeta");

        self::assertSame(['alfa', 'beta'], $this->texts->forward($path, new TextAnchor(), 10, 40)->lines);
    }

    public function testEmptyFileIsTextAndShowsNothing(): void
    {
        $path = $this->write('pusty.txt', '');

        self::assertNull($this->texts->refuse($path, null));
        self::assertSame([''], $this->texts->forward($path, new TextAnchor(), 10, 40)->lines);
        self::assertNull($this->texts->forward($path, new TextAnchor(), 10, 40)->scroll(), 'nie ma czego przewijać');
    }

    public function testCarriageReturnsFromWindowsLineEndingsDisappear(): void
    {
        $path = $this->write('crlf.txt', "alfa\r\nbeta\r\n");

        self::assertSame(['alfa', 'beta'], $this->texts->forward($path, new TextAnchor(), 10, 40)->lines);
    }

    /** Tabulator idzie do najbliższego przystanku, a nie o stałą liczbę spacji. */
    public function testTabsExpandToTabStops(): void
    {
        $path = $this->write('wciecia.txt', "\tjeden\nab\tdwa\n");

        self::assertSame(['    jeden', 'ab  dwa'], $this->texts->forward($path, new TextAnchor(), 10, 40)->lines);
    }

    public function testControlCharactersGetAVisibleMark(): void
    {
        $path = $this->write('sterujace.txt', "alfa\x01\x02beta\n");

        self::assertSame(['alfa··beta'], $this->texts->forward($path, new TextAnchor(), 10, 40)->lines);
    }

    /** Bajt spoza kodowania nie psuje klatki i nie wywraca aplikacji. */
    public function testInvalidByteBecomesAReplacementCharacter(): void
    {
        $path = $this->write('bledny.txt', "alfa\xC3\x28beta\n");

        $lines = $this->texts->forward($path, new TextAnchor(), 10, 40)->lines;

        self::assertCount(1, $lines);
        self::assertStringStartsWith('alfa', $lines[0]);
        self::assertStringEndsWith('beta', $lines[0]);
        self::assertTrue(mb_check_encoding($lines[0], 'UTF-8'), 'wiersz wychodzi stąd zawsze poprawnym UTF-8');
    }

    /** Kodowanie jednobajtowe rozpoznajemy i konwertujemy, zamiast pokazywać śmieci. */
    public function testSingleByteEncodingIsDetectedAndConverted(): void
    {
        $path = $this->write('iso.txt', "za\xBF\xF3\xB3\xE6 g\xEA\x9Bl\xB1 ja\xBC\xF1\n");

        $lines = $this->texts->forward($path, new TextAnchor(), 10, 40)->lines;

        self::assertTrue(mb_check_encoding($lines[0], 'UTF-8'));
        self::assertStringContainsString('ż', $lines[0], 'ogonki wyszły z ISO-8859-2, a nie z podmiany');
    }

    public function testUtf8ByteOrderMarkDoesNotShowUpInTheFirstLine(): void
    {
        $path = $this->write('bom.txt', "\xEF\xBB\xBFalfa\nbeta\n");

        self::assertSame(['alfa', 'beta'], $this->texts->forward($path, new TextAnchor(), 10, 40)->lines);
    }

    /**
     * UTF-16 i UTF-32 czytają się **wraz z całym rachunkiem bajtów**, a nie tylko
     * pierwszym oknem: trudnością był tu podział na wiersze i kotwice, nie sama
     * konwersja.
     *
     * @param non-empty-string $encoding
     */
    #[DataProvider('wideEncodings')]
    public function testWideEncodingsAreReadWithTheirOwnLineBreaks(string $encoding, string $bom): void
    {
        $path = $this->write('szeroki.txt', $bom . $this->encode("zażółć\ngęślą\njaźń\n", $encoding));

        self::assertNull($this->texts->refuse($path, null));

        $window = $this->texts->forward($path, new TextAnchor(), 2, 40);

        self::assertSame(['zażółć', 'gęślą'], $window->lines);
        self::assertSame(3, $window->next->line, 'numer wiersza liczy wiersze, nie bajty');

        $rest = $this->texts->forward($path, $window->next, 2, 40);

        self::assertSame(['jaźń'], $rest->lines);
        self::assertTrue(
            $this->texts->backward($path, $window->next, 2, 40)->equals(new TextAnchor()),
            'powrót o dwa wiersze wraca na sam początek, a nie w środek znaku',
        );
    }

    /** @return array<string, array{string, string}> */
    public static function wideEncodings(): array
    {
        return [
            'UTF-16LE' => ['UTF-16LE', "\xFF\xFE"],
            'UTF-16BE' => ['UTF-16BE', "\xFE\xFF"],
            'UTF-32LE' => ['UTF-32LE', "\xFF\xFE\x00\x00"],
            'UTF-32BE' => ['UTF-32BE', "\x00\x00\xFE\xFF"],
        ];
    }

    /** Znacznik kolejności bajtów nie pokazuje się w treści pierwszego wiersza. */
    public function testWideByteOrderMarkIsNotPartOfTheFirstLine(): void
    {
        $path = $this->write('bom16.txt', "\xFF\xFE" . $this->encode("alfa\n", 'UTF-16LE'));

        self::assertSame(['alfa'], $this->texts->forward($path, new TextAnchor(), 10, 40)->lines);
    }

    /**
     * Sedno obsługi UTF-16: bajty `0A 00` **w środku pary znaków** nie są końcem
     * wiersza.
     *
     * Znak U+0A39 zapisany w UTF-16LE to bajty `39 0A`, a stojący po nim U+2500 —
     * `00 25`; razem `39 0A 00 25`, w którym para `0A 00` siedzi na nieparzystym
     * przesunięciu. Wzięta za koniec wiersza rozjechałaby kotwicę o bajt, czyli
     * o pół znaku, i wszystko dalej byłoby śmieciem.
     */
    public function testMisalignedNewlineBytesAreNotALineBreak(): void
    {
        $path = $this->write(
            'pulapka.txt',
            "\xFF\xFE" . $this->encode("a\u{0A39}\u{2500}b\ndrugi\n", 'UTF-16LE'),
        );

        self::assertSame(
            ["a\u{0A39}\u{2500}b", 'drugi'],
            $this->texts->forward($path, new TextAnchor(), 10, 40)->lines,
        );
    }

    /** UTF-16 bez znacznika rozpoznajemy po wzorcu zer — ciasno, żeby nie brać binariów za tekst. */
    public function testUtf16WithoutByteOrderMarkIsRecognisedByItsPattern(): void
    {
        $path = $this->write('bez-bom.txt', $this->encode("pierwszy wiersz\ndrugi wiersz\n", 'UTF-16LE'));

        self::assertNull($this->texts->refuse($path, null));
        self::assertSame(
            ['pierwszy wiersz', 'drugi wiersz'],
            $this->texts->forward($path, new TextAnchor(), 10, 40)->lines,
        );
    }

    /** …a plik binarny gęsty od zer nadal binariem zostaje. */
    public function testZeroHeavyBinaryIsNotMistakenForWideText(): void
    {
        $path = $this->write('dane.bin', str_repeat("\x00\x00\x01\x02\x00\x00\xFF\xFE", 64));

        self::assertSame('module.file-info.preview.binary', $this->texts->refuse($path, null));
    }

    public function testBinaryFileIsRefused(): void
    {
        $path = $this->write('obraz.dat', "\x89PNG\r\n\x1a\n" . str_repeat("\x00\x01\x02", 100));

        self::assertSame('module.file-info.preview.binary', $this->texts->refuse($path, null));
    }

    /** Pierwszy stopień kaskady: rozszerzenie rozstrzyga bez dotykania treści. */
    public function testKnownExtensionWinsOverTheContentSniff(): void
    {
        $path = $this->write('skrypt.php', "<?php\necho 1;\n");

        self::assertNull($this->texts->refuse($path, null));
    }

    /** Drugi stopień: opis od `file` dla pliku bez rozszerzenia. */
    public function testDescriptionFromFileCommandDecidesWhenTheExtensionDoesNot(): void
    {
        $path = $this->write('README', "tresc\n");

        self::assertNull($this->texts->refuse($path, 'ASCII text'));
    }

    /** Trzeci stopień: podejrzenie bajtów — jedyne, które rozstrzyga zawsze. */
    public function testByteSniffDecidesWhenNeitherExtensionNorDescriptionDoes(): void
    {
        $path = $this->write('notatka', "zwykły tekst bez rozszerzenia\n");

        self::assertNull($this->texts->refuse($path, 'data'));
    }

    public function testUnreadableFileIsRefusedWithAReason(): void
    {
        self::assertSame(
            'module.file-info.preview.unreadable',
            $this->texts->refuse($this->directory . '/nie-ma-mnie.txt', null),
        );
    }

    /**
     * Wiersz dłuższy od okna nie kończy się w oknie — i to jest w porządku:
     * przewinięcie w dół wchodzi w jego głąb, a **numer wiersza nie rośnie**,
     * bo wiersz się nie skończył.
     */
    public function testLineLongerThanTheWindowIsEnteredRatherThanSkipped(): void
    {
        $path = $this->write('jeden-wiersz.json', str_repeat('x', 200_000) . "\n");

        $window = $this->texts->forward($path, new TextAnchor(), 4, 20);

        self::assertCount(1, $window->lines);
        self::assertSame(20, mb_strlen($window->lines[0]), 'czytamy tyle znaków, ile pokażemy');
        self::assertGreaterThan(0, $window->next->byte);
        self::assertSame(1, $window->next->line, 'ten sam wiersz, dalsze bajty');
    }

    /**
     * Sedno kroku: **plik o wielkości pół gigabajta nie zatrzymuje klatki**.
     *
     * Sprawdza to test, a nie wrażenie — i sprawdza obie strony naraz: odczyt
     * z początku, ze środka i powrót w górę. Plik powstaje rzadki (`fseek` poza
     * koniec), więc kosztuje bajty, a nie pół gigabajta miejsca; dla `fread`
     * i `filesize` jest nieodróżnialny od gęstego.
     */
    public function testHalfAGigabyteFileIsReadInWindowTime(): void
    {
        $path = $this->directory . '/wielki.txt';
        $handle = fopen($path, 'wb');
        self::assertNotFalse($handle);
        fwrite($handle, "pierwszy wiersz\n");
        fseek($handle, 512 * 1024 * 1024);
        fwrite($handle, "ostatni wiersz\n");
        fclose($handle);

        $started = microtime(true);
        $head = $this->texts->forward($path, new TextAnchor(), 40, 200);
        $middle = $this->texts->forward($path, new TextAnchor(256 * 1024 * 1024, 2), 40, 200);
        $back = $this->texts->backward($path, new TextAnchor(512 * 1024 * 1024, 2), 40, 200);
        $elapsed = microtime(true) - $started;

        self::assertSame('pierwszy wiersz', $head->lines[0]);
        self::assertSame(512 * 1024 * 1024 + 15, $head->fileBytes);
        self::assertNotSame([], $middle->lines, 'środek pliku też coś pokazuje');
        self::assertLessThanOrEqual(512 * 1024 * 1024, $back->byte);
        self::assertLessThan(
            0.033,
            $elapsed,
            'trzy odczyty z pliku półgigabajtowego mieszczą się w jednej klatce',
        );
    }

    private function write(string $name, string $content): string
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    /** @param non-empty-string $encoding */
    private function encode(string $text, string $encoding): string
    {
        return mb_convert_encoding($text, $encoding, 'UTF-8');
    }
}
