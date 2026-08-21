<?php

declare(strict_types=1);

namespace LightManager\Tests\Documentation;

use LightManager\Tests\Support\DocumentationTree;
use PHPUnit\Framework\TestCase;

/**
 * **Skrót zgadza się ze źródłem** (krok 66, zobowiązanie z kroku 62).
 *
 * `SKILL.md` jest streszczeniem architektury, nie drugim zdaniem w sprawie —
 * a rozjazd między nimi już raz w tym projekcie powstał i przez jakiś czas nikt
 * go nie widział, bo pierwszeństwo źródła było zapisane jednym zdaniem
 * w **trzecim** pliku i nikt go nie pilnował.
 *
 * Test nie porównuje treści reguły ze zdaniem rozdziału — tego maszyna nie
 * potrafi. Pilnuje **adresów i numerów**, czyli tego, co rozjeżdża się pierwsze:
 * reguła dopisana do skrótu bez rozdziału, rozdział przeniesiony bez poprawienia
 * skrótu, numer wskazany w tabeli, którego w spisie reguł nie ma.
 *
 * Rozdział bez ani jednej reguły jest przy tym **legalny** i nie ma go po co
 * wpisywać do skrótu: `09-co-dalej.md` to mapa kroków wdrożenia, a nie reguła
 * pisania kodu. Odwrotność już legalna nie jest — rozdział, którego nie ma
 * w spisie rozdziałów, jest rozdziałem, do którego nikt nie trafi.
 */
final class SkillMatchesArchitectureTest extends TestCase
{
    private const SKILL = '.claude/skills/light-manager-conventions/SKILL.md';

    private const INDEX = 'docs/architecture.md';

    private const CHAPTERS = 'docs/architektura';

    public function testEveryChapterNamedInTheSkillExists(): void
    {
        $missing = [];

        foreach (array_keys(self::claims()) as $path) {
            if (!file_exists(DocumentationTree::root() . '/' . $path)) {
                $missing[] = $path;
            }
        }

        self::assertSame([], $missing, 'skrót wskazuje nieistniejące rozdziały: ' . implode(', ', $missing));
    }

    public function testEveryHardRuleIsAssignedToAChapter(): void
    {
        $assigned = [];

        foreach (self::claims() as $numbers) {
            foreach ($numbers as $number) {
                $assigned[$number] = true;
            }
        }

        $orphans = array_values(array_diff(self::ruleNumbers(), array_keys($assigned)));

        self::assertSame(
            [],
            $orphans,
            'reguły bez rozdziału w tabeli skrótu: ' . implode(', ', array_map(strval(...), $orphans)),
        );
    }

    public function testEveryNumberClaimedByAChapterIsAnExistingRule(): void
    {
        $rules = self::ruleNumbers();
        $unknown = [];

        foreach (self::claims() as $path => $numbers) {
            foreach ($numbers as $number) {
                if (!in_array($number, $rules, true)) {
                    $unknown[] = $path . ': reguła ' . $number;
                }
            }
        }

        self::assertSame([], $unknown, 'tabela skrótu wskazuje nieistniejące reguły: ' . implode(', ', $unknown));
    }

    /** Numeracja bez dziur i bez powtórzeń — inaczej „reguła 12" znaczy dwie rzeczy naraz. */
    public function testRuleNumbersRunFromOneWithoutGaps(): void
    {
        $numbers = self::ruleNumbers();

        self::assertSame(range(1, count($numbers)), $numbers, 'numeracja reguł w skrócie ma dziurę albo powtórzenie');
    }

    public function testEveryChapterFileIsListedInTheIndex(): void
    {
        $listed = [];

        foreach (DocumentationTree::links(self::INDEX) as $link) {
            $listed[explode('#', $link['target'])[0]] = true;
        }

        $missing = [];

        foreach (self::chapterFiles() as $chapter) {
            if (!isset($listed[$chapter])) {
                $missing[] = $chapter;
            }
        }

        self::assertSame([], $missing, 'rozdziały spoza spisu w ' . self::INDEX . ': ' . implode(', ', $missing));
    }

    /**
     * Rozdział → numery reguł, które tabela skrótu mu przypisuje.
     *
     * @return array<string, list<int>>
     */
    private static function claims(): array
    {
        $claims = [];

        foreach (DocumentationTree::prose(self::SKILL) as $line) {
            if (preg_match('/^\|\s*`(docs\/architektura\/[^`]+)`\s*\|(.*)\|$/u', trim($line), $matched) !== 1) {
                continue;
            }

            $numbers = [];

            if (preg_match('/\((?:reguły|reguła) ([^)]+)\)/u', $matched[2], $found) === 1) {
                foreach (explode(',', $found[1]) as $token) {
                    $token = trim($token);

                    if (preg_match('/^\d+$/', $token) === 1) {
                        $numbers[] = (int) $token;
                    }
                }
            }

            $claims[rtrim($matched[1], '/')] = $numbers;
        }

        return $claims;
    }

    /**
     * Numery reguł twardych — pozycje najwyższego poziomu listy numerowanej.
     *
     * @return list<int>
     */
    private static function ruleNumbers(): array
    {
        $numbers = [];
        $inside = false;

        foreach (DocumentationTree::prose(self::SKILL) as $line) {
            if (str_starts_with($line, '## ')) {
                $inside = str_contains($line, 'Twarde reguły');

                continue;
            }

            if ($inside && preg_match('/^(\d+)\. /', $line, $matched) === 1) {
                $numbers[] = (int) $matched[1];
            }
        }

        return $numbers;
    }

    /** @return list<string> */
    private static function chapterFiles(): array
    {
        $files = [];

        foreach (DocumentationTree::documents() as $document) {
            if (str_starts_with($document, self::CHAPTERS . '/')) {
                $files[] = substr($document, strlen('docs/'));
            }
        }

        return $files;
    }
}
