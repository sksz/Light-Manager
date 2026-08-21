<?php

declare(strict_types=1);

namespace LightManager\Tests\Documentation;

use LightManager\Tests\Support\DocumentationTree;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * **Wskazanie na kod jest prawdziwe, a przykład ma odbiorcę** (krok 66).
 *
 * Konwencja z kroku 62 mówi: *przykład kodu jest plikiem, a dokument go
 * wskazuje — nie kopiuje*, wraz z zakresem wierszy. Sama analiza `examples/`
 * przez PHPStana stoi od tamtego kroku; **ten test pilnuje wskazań**, czyli
 * tego, czego PHPStan nie widzi:
 *
 * 1. wskazany plik istnieje,
 * 2. wskazany zakres **mieści się w jego długości** — bo plik po dopisaniu
 *    metody przesuwa wiersze i zakres zaczyna pokazywać co innego, i to po
 *    cichu,
 * 3. **każdy plik w `examples/` jest przez coś wskazany** — przykład, do
 *    którego nie prowadzi żaden dokument, jest kodem bez odbiorcy (reguła 13),
 *    a katalog przykładów jest ostatnim miejscem, w którym taki kod powinien
 *    leżeć.
 *
 * Punkt trzeci ma jeden wyjątek nazwany wprost: **pliki napisów**
 * (`lang/pl.php`, `lang/en.php`) wskazuje się razem z modułem, którego są
 * częścią, a nie osobno.
 */
final class DocumentationExamplesTest extends TestCase
{
    /** Wskazanie: odnośnik do pliku `.php`, po nim opcjonalny zakres wierszy. */
    private const POINTER = '/\[[^\]]*\]\(([^)\s]+\.php)\)(?:,?\s*(?:wiersze|lines)\s+(\d+)[–-](\d+)|,?\s*(?:wiersz|line)\s+(\d+))?/u';

    #[DataProvider('documents')]
    public function testEveryPointedRangeFitsInTheFile(string $document): void
    {
        $wrong = [];
        $directory = dirname(DocumentationTree::root() . '/' . $document);

        foreach (DocumentationTree::prose($document) as $number => $line) {
            preg_match_all(self::POINTER, $line, $matches, PREG_SET_ORDER);

            foreach ($matches as $pointer) {
                $path = $directory . '/' . $pointer[1];

                if (!is_file($path)) {
                    $wrong[] = sprintf('wiersz %d: brak pliku %s', $number, $pointer[1]);

                    continue;
                }

                $length = count(file($path) ?: []);

                foreach ([$pointer[2] ?? '', $pointer[3] ?? '', $pointer[4] ?? ''] as $pointed) {
                    if ($pointed !== '' && (int) $pointed > $length) {
                        $wrong[] = sprintf(
                            'wiersz %d: %s ma %d wierszy, wskazano %s',
                            $number,
                            $pointer[1],
                            $length,
                            $pointed,
                        );
                    }
                }
            }
        }

        self::assertSame([], $wrong, $document . ' — wskazania nietrafione: ' . implode('; ', $wrong));
    }

    /** Zakres pisze się półpauzą, a pojedynczy wiersz — słowem w liczbie pojedynczej. */
    #[DataProvider('documents')]
    public function testEveryRangeIsWrittenWithAnEnDash(string $document): void
    {
        $wrong = [];

        foreach (DocumentationTree::prose($document) as $number => $line) {
            if (preg_match('/(?:wiersze|lines)\s+\d+-\d+/u', $line) === 1) {
                $wrong[] = 'wiersz ' . $number;
            }
        }

        self::assertSame([], $wrong, $document . ' — zakres łącznikiem zamiast półpauzy: ' . implode(', ', $wrong));
    }

    public function testEveryExampleIsPointedAtByADocument(): void
    {
        $pointed = [];

        foreach (DocumentationTree::documents() as $document) {
            $directory = dirname(DocumentationTree::root() . '/' . $document);

            foreach (DocumentationTree::prose($document) as $line) {
                preg_match_all(self::POINTER, $line, $matches);

                foreach ($matches[1] as $target) {
                    $real = realpath($directory . '/' . $target);

                    if ($real !== false) {
                        $pointed[$real] = true;
                    }
                }
            }
        }

        $orphans = [];

        foreach (self::examples() as $example) {
            if (!isset($pointed[$example]) && !str_contains($example, '/lang/')) {
                $orphans[] = substr($example, strlen(DocumentationTree::root()) + 1);
            }
        }

        sort($orphans);

        self::assertSame([], $orphans, 'przykłady, do których nie prowadzi żaden dokument: ' . implode(', ', $orphans));
    }

    /** @return iterable<string, array{string}> */
    public static function documents(): iterable
    {
        foreach (DocumentationTree::documents() as $document) {
            yield $document => [$document];
        }
    }

    /** @return list<string> */
    private static function examples(): array
    {
        $found = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(DocumentationTree::root() . '/examples', \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }
}
