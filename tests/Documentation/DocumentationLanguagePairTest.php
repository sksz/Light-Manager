<?php

declare(strict_types=1);

namespace LightManager\Tests\Documentation;

use LightManager\Tests\Support\DocumentationTree;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * **Para językowa nie rozjeżdża się w ciszy** (krok 66).
 *
 * Polski jest źródłem, angielski tłumaczeniem (D97 nr 3) — a wersja, która
 * została w tyle, ma być **usterką widoczną w bramce**, nie stanem normalnym.
 * Bez tego testu rozjazd jest niewidoczny z tego samego powodu, dla którego
 * niewidoczny jest brakujący napis: strona, do której nic nie dopisano, wygląda
 * dokładnie tak samo jak strona, do której nie było czego dopisać.
 *
 * Test pilnuje **kształtu**, a nie treści, i granica jest tu świadoma:
 * tłumaczy człowiek (zobacz „Poza zakresem" kroku 66), więc maszyna sprawdza
 * to, co da się sprawdzić bez rozumienia zdania — **te same pliki, tyle samo
 * nagłówków na tych samych poziomach, tyle samo diagramów i tyle samo spisów**.
 * Treść tabel porównuje się z kodem osobno, w obu językach naraz, i robią to
 * trzy pozostałe testy zgodności.
 *
 * Nazwa pliku jest przy tym **w języku swojego drzewa**
 * (`01-czym-to-jest.md` ↔ `01-what-is-it.md`), więc parę wyznacza **numer**,
 * a nie nazwa — inaczej konwencja z kroku 62 i ten test wykluczałyby się
 * nawzajem.
 */
final class DocumentationLanguagePairTest extends TestCase
{
    /** Katalog polski → jego angielski odpowiednik. */
    private const TREES = [
        'podrecznik' => 'manual',
        'przewodnik' => 'guide',
        'onboarding' => 'onboarding',
    ];

    #[DataProvider('trees')]
    public function testBothTreesHaveTheSameDocuments(string $polish, string $english): void
    {
        self::assertSame(
            array_keys(self::documentsOf('pl', $polish)),
            array_keys(self::documentsOf('en', $english)),
            'drzewa ' . $polish . ' i ' . $english . ' mają inne dokumenty',
        );
    }

    #[DataProvider('pairs')]
    public function testBothDocumentsHaveTheSameHeadingShape(string $polish, string $english): void
    {
        self::assertSame(
            array_map(static fn (array $heading): int => $heading['level'], DocumentationTree::headings($polish)),
            array_map(static fn (array $heading): int => $heading['level'], DocumentationTree::headings($english)),
            $polish . ' i ' . $english . ' mają inny układ nagłówków',
        );
    }

    #[DataProvider('pairs')]
    public function testBothDocumentsCarryTheSameDiagramsAndLists(string $polish, string $english): void
    {
        self::assertCount(
            count(DocumentationTree::diagrams($polish)),
            DocumentationTree::diagrams($english),
            $polish . ' i ' . $english . ' mają inną liczbę diagramów',
        );

        self::assertSame(
            array_keys(DocumentationTree::lists($polish)),
            array_keys(DocumentationTree::lists($english)),
            $polish . ' i ' . $english . ' mają inne spisy pod znacznikiem',
        );
    }

    /**
     * **Każdy diagram ma przed sobą zdanie mówiące to samo słowami** — cena
     * wyboru mermaida, zamieniona na regułę (D97 nr 2).
     *
     * Sprawdzalne maszynowo jest to, czy akapit **jest**; czy mówi to samo,
     * sprawdza człowiek. Nagłówek, wiersz tabeli i drugi blok kodu akapitem nie
     * są — bo czytelnik `cat`a i czytnik ekranu nie dostają z nich niczego
     * ponad to, co widzą w samym źródle diagramu.
     */
    #[DataProvider('documentsWithDiagrams')]
    public function testEveryDiagramIsPrecededByASentence(string $document): void
    {
        $lines = explode("\n", DocumentationTree::read($document));
        $without = [];

        foreach (DocumentationTree::diagrams($document) as $start) {
            $paragraph = '';

            for ($index = $start - 2; $index >= 0; --$index) {
                $line = trim($lines[$index]);

                if ($line === '') {
                    continue;
                }

                $paragraph = $line;

                break;
            }

            if ($paragraph === '' || str_starts_with($paragraph, '#') || str_starts_with($paragraph, '|') || str_starts_with($paragraph, '```')) {
                $without[] = 'wiersz ' . $start;
            }
        }

        self::assertSame([], $without, $document . ' — diagram bez zdania opisowego: ' . implode(', ', $without));
    }

    /** @return iterable<string, array{string, string}> */
    public static function trees(): iterable
    {
        foreach (self::TREES as $polish => $english) {
            yield $polish => [$polish, $english];
        }
    }

    /** @return iterable<string, array{string, string}> */
    public static function pairs(): iterable
    {
        foreach (self::TREES as $polish => $english) {
            $left = self::documentsOf('pl', $polish);
            $right = self::documentsOf('en', $english);

            foreach ($left as $number => $document) {
                if (isset($right[$number])) {
                    yield $polish . '/' . $number => [$document, $right[$number]];
                }
            }
        }

        yield 'README' => ['docs/pl/README.md', 'docs/en/README.md'];
    }

    /** @return iterable<string, array{string}> */
    public static function documentsWithDiagrams(): iterable
    {
        foreach (DocumentationTree::documents() as $document) {
            if (DocumentationTree::diagrams($document) !== []) {
                yield $document => [$document];
            }
        }
    }

    /**
     * Dokumenty drzewa pod kluczem, który wyznacza parę: numer z nazwy pliku,
     * a dla spisu — słowo `README`.
     *
     * @return array<string, string>
     */
    private static function documentsOf(string $language, string $tree): array
    {
        $documents = [];

        foreach (DocumentationTree::language($language) as $document) {
            if (!str_starts_with($document, 'docs/' . $language . '/' . $tree . '/')) {
                continue;
            }

            $name = basename($document, '.md');
            $documents[preg_match('/^(\d+)-/', $name, $matched) === 1 ? $matched[1] : $name] = $document;
        }

        ksort($documents);

        return $documents;
    }
}
