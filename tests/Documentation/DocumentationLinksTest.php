<?php

declare(strict_types=1);

namespace LightManager\Tests\Documentation;

use LightManager\Tests\Support\DocumentationTree;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * **Odnośnik prowadzi tam, gdzie obiecuje** (krok 66).
 *
 * Pierwszy z siedmiu testów zgodności i najprostszy z nich — a zarazem ten,
 * który miał najwięcej powodów, żeby się zaczerwienić: krok 62 rozbił dokument
 * źródłowy na dziewięć rozdziałów, a wskazywało do niego **pięćdziesiąt jeden
 * plików**, w tym czterdzieści leżących w archiwum planów, którego się nie
 * przepisuje.
 *
 * Kotwica liczy się wedle reguły GitHuba (`DocumentationTree::anchor()`), bo to
 * ona rozstrzyga, czy odnośnik zadziała w przeglądarce. Reguła ma jeden
 * nieoczywisty skutek: **dwie spacje w nagłówku dają dwa myślniki** — stąd
 * `#d1--zakres-pierwszej-iteracji-minimalny` w dzienniku decyzji, gdzie numer
 * oddziela od tytułu półpauza.
 */
final class DocumentationLinksTest extends TestCase
{
    #[DataProvider('documents')]
    public function testEveryRelativeLinkPointsAtAnExistingFile(string $document): void
    {
        $broken = [];
        $directory = dirname(DocumentationTree::root() . '/' . $document);

        foreach (DocumentationTree::links($document) as $link) {
            $path = explode('#', $link['target'])[0];

            if ($path === '') {
                continue;
            }

            if (!file_exists($directory . '/' . $path)) {
                $broken[] = sprintf('wiersz %d: %s', $link['line'], $link['target']);
            }
        }

        self::assertSame([], $broken, $document . ' — odnośniki donikąd: ' . implode(', ', $broken));
    }

    #[DataProvider('documents')]
    public function testEveryAnchorPointsAtAnExistingHeading(string $document): void
    {
        $broken = [];
        $directory = dirname(DocumentationTree::root() . '/' . $document);

        foreach (DocumentationTree::links($document) as $link) {
            [$path, $anchor] = array_pad(explode('#', $link['target'], 2), 2, '');

            if ($anchor === '') {
                continue;
            }

            $target = $path === '' ? $document : self::relative($directory . '/' . $path);

            if ($target === null || !str_ends_with($target, '.md')) {
                continue;
            }

            if (!in_array(DocumentationTree::anchor($anchor), self::anchorsOf($target), true)) {
                $broken[] = sprintf('wiersz %d: %s', $link['line'], $link['target']);
            }
        }

        self::assertSame([], $broken, $document . ' — kotwice donikąd: ' . implode(', ', $broken));
    }

    /** @return iterable<string, array{string}> */
    public static function documents(): iterable
    {
        foreach (DocumentationTree::documents() as $document) {
            yield $document => [$document];
        }
    }

    /** @return list<string> */
    private static function anchorsOf(string $document): array
    {
        return array_map(
            static fn (array $heading): string => DocumentationTree::anchor($heading['text']),
            DocumentationTree::headings($document),
        );
    }

    /** Ścieżka względna wobec korzenia; `null`, gdy cel leży poza repozytorium. */
    private static function relative(string $path): ?string
    {
        $real = realpath($path);
        $root = DocumentationTree::root() . '/';

        if ($real === false || !str_starts_with($real, $root)) {
            return null;
        }

        return substr($real, strlen($root));
    }
}
