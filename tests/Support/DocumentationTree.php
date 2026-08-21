<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

/**
 * **Jeden rachunek dla siedmiu testów zgodności** (krok 66).
 *
 * Każdy z testów w `tests/Documentation/` potrzebuje tego samego: listy
 * dokumentów, ich nagłówków, odnośników, bloków diagramów i **spisów objętych
 * znacznikiem**. Siedem osobnych odczytów rozjechałoby się przy pierwszej
 * zmianie formatu — dokładnie tak, jak rozjeżdżają się spisy, których te testy
 * pilnują.
 *
 * Trzy rozstrzygnięcia, na których stoi ta klasa:
 *
 * 1. **Blok kodu jest ilustracją, nie obietnicą.** Odnośniki, wskazania na
 *    `examples/` i znaczniki spisów czyta się **poza** blokami ```` ``` ````.
 *    Dokument obiecuje czytelnikowi coś w prozie; to, co pokazuje w bloku,
 *    pokazuje jako przykład — a przykładowy odnośnik do nieistniejącego pliku
 *    jest poprawnym przykładem.
 * 2. **Kotwica liczy się wedle reguły GitHuba**: małe litery, znaki spoza
 *    `\w`, spacji i myślnika usunięte, każda spacja zamieniona na myślnik.
 *    Dwie spacje dają więc dwa myślniki — stąd `#d1--zakres…` w dzienniku
 *    decyzji.
 * 3. **Spis jest tabelą markdown w znacznikach HTML.** Znacznik mówi testowi,
 *    gdzie patrzeć, a autorowi — że wiersze poniżej są kopią stanu kodu i nie
 *    pisze się ich z głowy.
 */
final class DocumentationTree
{
    /** Katalogi i pliki, w których mieszka dokumentacja projektu. */
    private const DOCUMENTS = ['docs', 'examples', '.claude'];

    private const SINGLE_FILES = ['README.md', 'CHANGELOG.md', 'CLAUDE.md'];

    /** @var array<string, string> ścieżka względna → treść */
    private static array $cache = [];

    private function __construct()
    {
    }

    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Wszystkie dokumenty markdown repozytorium, ścieżkami względnymi wobec
     * korzenia.
     *
     * @return list<string>
     */
    public static function documents(): array
    {
        $found = self::SINGLE_FILES;

        foreach (self::DOCUMENTS as $directory) {
            foreach (self::markdownIn(self::root() . '/' . $directory) as $path) {
                $found[] = substr($path, strlen(self::root()) + 1);
            }
        }

        sort($found);

        return array_values(array_filter($found, static fn (string $path): bool => is_file(self::root() . '/' . $path)));
    }

    /**
     * Dokumenty jednego z dwóch drzew językowych.
     *
     * @return list<string>
     */
    public static function language(string $code): array
    {
        return array_values(array_filter(
            self::documents(),
            static fn (string $path): bool => str_starts_with($path, 'docs/' . $code . '/'),
        ));
    }

    public static function read(string $relative): string
    {
        return self::$cache[$relative] ??= (string) file_get_contents(self::root() . '/' . $relative);
    }

    /**
     * Wiersze dokumentu **poza blokami kodu**, wraz z numerami (od 1).
     *
     * @return array<int, string>
     */
    public static function prose(string $relative): array
    {
        $prose = [];
        $fenced = false;

        foreach (explode("\n", self::read($relative)) as $index => $line) {
            if (str_starts_with(ltrim($line), '```')) {
                $fenced = !$fenced;

                continue;
            }

            if (!$fenced) {
                $prose[$index + 1] = $line;
            }
        }

        return $prose;
    }

    /**
     * Odnośniki względne dokumentu — bez adresów `http` i `mailto`.
     *
     * @return list<array{target: string, line: int}>
     */
    public static function links(string $relative): array
    {
        $links = [];

        foreach (self::prose($relative) as $number => $line) {
            preg_match_all('/\[[^\]]*\]\(([^)\s]+)\)/', $line, $matches);

            foreach ($matches[1] as $target) {
                if (str_starts_with($target, 'http') || str_starts_with($target, 'mailto:')) {
                    continue;
                }

                $links[] = ['target' => $target, 'line' => $number];
            }
        }

        return $links;
    }

    /**
     * Nagłówki dokumentu: poziom, treść i numer wiersza.
     *
     * @return list<array{level: int, text: string, line: int}>
     */
    public static function headings(string $relative): array
    {
        $headings = [];

        foreach (self::prose($relative) as $number => $line) {
            if (preg_match('/^(#{1,6}) (.+)$/', $line, $matched) === 1) {
                $headings[] = ['level' => strlen($matched[1]), 'text' => trim($matched[2]), 'line' => $number];
            }
        }

        return $headings;
    }

    /** Kotwica nagłówka wedle reguły GitHuba. */
    public static function anchor(string $heading): string
    {
        $text = mb_strtolower(trim($heading));
        $text = (string) preg_replace('/[^\p{L}\p{N}\s_-]+/u', '', $text);

        return str_replace(' ', '-', $text);
    }

    /**
     * Numery wierszy, w których zaczyna się blok ```` ```mermaid ````.
     *
     * Wykrywanie idzie po **początku wiersza**, więc wzmianka o bloku w zdaniu
     * nie jest blokiem — a takie zdania stoją w planach i w konwencji.
     *
     * @return list<int>
     */
    public static function diagrams(string $relative): array
    {
        $lines = [];

        foreach (explode("\n", self::read($relative)) as $index => $line) {
            if (str_starts_with(ltrim($line), '```mermaid')) {
                $lines[] = $index + 1;
            }
        }

        return $lines;
    }

    /**
     * Spisy objęte znacznikiem: `<!-- spis:nazwa -->` … `<!-- /spis -->`.
     *
     * Czytane **poza blokami kodu**, i to nie jest drobiazg: przewodnik
     * pokazuje w bloku, jak taki znacznik wygląda, a ilustracja o tej samej
     * nazwie nadpisałaby spis prawdziwy — cicho, bo tabela przykładowa jest
     * poprawną tabelą.
     *
     * Wiersz tabeli wraca **rozbity na komórki**, bez wiersza nagłówka i bez
     * kreski oddzielającej. Nagłówek zostaje osobno, bo test kolumn też go
     * potrzebuje.
     *
     * @return array<string, array{columns: list<string>, rows: list<list<string>>, line: int}>
     */
    public static function lists(string $relative): array
    {
        $lists = [];
        $name = null;
        $rows = [];
        $columns = [];
        $start = 0;

        foreach (self::prose($relative) as $number => $line) {
            $trimmed = trim($line);

            if (preg_match('/^<!--\s*spis:([a-z0-9:-]+)\s*-->$/', $trimmed, $matched) === 1) {
                $name = $matched[1];
                $rows = [];
                $columns = [];
                $start = $number;

                continue;
            }

            if ($name === null) {
                continue;
            }

            if ($trimmed === '<!-- /spis -->') {
                $lists[$name] = ['columns' => $columns, 'rows' => $rows, 'line' => $start];
                $name = null;

                continue;
            }

            if (!str_starts_with($trimmed, '|')) {
                continue;
            }

            $cells = self::cells($trimmed);

            if ($cells === [] || preg_match('/^:?-{3,}:?$/', $cells[0]) === 1) {
                continue;
            }

            if ($columns === []) {
                $columns = $cells;

                continue;
            }

            $rows[] = $cells;
        }

        return $lists;
    }

    /**
     * Wszystkie spisy drzewa: nazwa znacznika → dokument → zawartość.
     *
     * @return array<string, array<string, array{columns: list<string>, rows: list<list<string>>, line: int}>>
     */
    public static function allLists(): array
    {
        $all = [];

        foreach (self::documents() as $document) {
            foreach (self::lists($document) as $name => $list) {
                $all[$name][$document] = $list;
            }
        }

        return $all;
    }

    /**
     * Napis oczyszczony z ozdobników markdowna — do porównania z tym, co
     * naprawdę mówi kod.
     */
    public static function plain(string $cell): string
    {
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $cell) ?? $cell;

        // Treść w grawisach jest **dosłowna** i wyjmuje się ją przed zdejmowaniem
        // wyróżnień — inaczej `*` (odwrócenie zaznaczenia) znikałby jako
        // markdownowa gwiazdka, a wiersz spisu zostawałby bez klawisza.
        $literals = [];
        $text = (string) preg_replace_callback(
            '/`([^`]*)`/',
            static function (array $matched) use (&$literals): string {
                $literals[] = $matched[1];

                return "\0" . (count($literals) - 1) . "\0";
            },
            $text,
        );

        $text = str_replace(['**', '*', '_'], '', $text);

        foreach ($literals as $index => $literal) {
            $text = str_replace("\0" . $index . "\0", $literal, $text);
        }

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** @return list<string> */
    private static function cells(string $row): array
    {
        $inner = trim($row, '|');
        return array_map(trim(...), explode('|', $inner));
    }

    /** @return list<string> */
    private static function markdownIn(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $found = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'md') {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }
}
