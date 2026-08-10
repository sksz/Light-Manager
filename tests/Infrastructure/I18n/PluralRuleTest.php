<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\I18n;

use LightManager\Application\Dto\Language;
use LightManager\Infrastructure\I18n\PluralRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Reguła liczby mnogiej — jedyne miejsce, w którym zapisano, że po polsku
 * dwanaście plików to nie „dwanaście pliki”.
 */
final class PluralRuleTest extends TestCase
{
    /** @return array<string, array{int, int}> */
    public static function slavicCounts(): array
    {
        return [
            'jeden' => [1, 0],
            'dwa' => [2, 1],
            'cztery' => [4, 1],
            'pięć' => [5, 2],
            'zero' => [0, 2],
            'jedenaście' => [11, 2],
            'dwanaście — końcówka myli' => [12, 2],
            'czternaście' => [14, 2],
            'dwadzieścia dwa' => [22, 1],
            'dwadzieścia pięć' => [25, 2],
            'sto dwa' => [102, 1],
            'sto dwanaście' => [112, 2],
            'dwadzieścia jeden — nie jedynka' => [21, 2],
        ];
    }

    #[DataProvider('slavicCounts')]
    public function testPolishHasThreeFormsWithTeensAsAnException(int $count, int $expected): void
    {
        self::assertSame($expected, PluralRule::Slavic->formFor($count));
    }

    /** @return array<string, array{int, int}> */
    public static function germanicCounts(): array
    {
        return [
            'jeden' => [1, 0],
            'zero' => [0, 1],
            'dwa' => [2, 1],
            'dwadzieścia jeden' => [21, 1],
        ];
    }

    #[DataProvider('germanicCounts')]
    public function testEnglishSplitsOnlyTheSingular(int $count, int $expected): void
    {
        self::assertSame($expected, PluralRule::Germanic->formFor($count));
    }

    public function testRuleFollowsTheLanguage(): void
    {
        self::assertSame(PluralRule::Slavic, PluralRule::forLanguage(Language::Polish));
        self::assertSame(PluralRule::Germanic, PluralRule::forLanguage(Language::English));
    }

    /** Liczba ujemna nie ma prawa wyjść poza listę form. */
    public function testNegativeCountLandsInsideTheForms(): void
    {
        foreach (PluralRule::cases() as $rule) {
            self::assertLessThan($rule->forms(), $rule->formFor(-3));
            self::assertGreaterThanOrEqual(0, $rule->formFor(-3));
        }
    }
}
