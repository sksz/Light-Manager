<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\I18n;

use LightManager\Application\Dto\Language;
use LightManager\Infrastructure\I18n\Catalog;
use LightManager\Infrastructure\I18n\PluralRule;
use LightManager\Infrastructure\I18n\TranslatorService;
use LightManager\Tests\Support\PinsLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Katalog napisów i sposób sięgania po nie.
 *
 * Dwie rzeczy są tu ważniejsze od pojedynczych tłumaczeń: żaden język nie ma
 * prawa zgubić klucza, który ma inny, i żadna ścieżka nie ma prawa się
 * wywrócić — brak klucza czy brak języka kończy się gorszym napisem, nigdy
 * przerwaną klatką.
 */
final class TranslatorServiceTest extends TestCase
{
    use PinsLanguage;

    protected function tearDown(): void
    {
        $this->unpinLanguage();
    }

    private static function catalogDirectory(): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'lang';
    }

    public function testEveryLanguageCarriesTheSameKeys(): void
    {
        $reference = Catalog::load(Language::FALLBACK, self::catalogDirectory());

        self::assertNotNull($reference);

        foreach (Language::catalogued() as $language) {
            $catalog = Catalog::load($language, self::catalogDirectory());

            self::assertNotNull($catalog, 'język ' . $language->value . ' ma własny plik napisów');
            self::assertSame(
                $reference->keys(),
                $catalog->keys(),
                'katalog ' . $language->value . ' zgadza się co do klucza z językiem zapasowym',
            );
        }
    }

    /** Forma mnoga bez kompletu form zostawiłaby liczebnik bez napisu. */
    public function testPluralEntriesHaveAsManyFormsAsTheLanguageNeeds(): void
    {
        foreach (Language::catalogued() as $language) {
            $catalog = Catalog::load($language, self::catalogDirectory());
            self::assertNotNull($catalog);

            $expected = PluralRule::forLanguage($language)->forms();

            foreach ($catalog->keys() as $key) {
                $forms = $catalog->forms($key);

                if ($forms !== null) {
                    self::assertCount($expected, $forms, $language->value . ': ' . $key);
                }
            }
        }
    }

    /**
     * Napisy modułów wchodzą do katalogu **wyłącznie** pod przedrostkiem
     * `module.<id>.`; klucz spoza niego jest pomijany i wraca przez `ignored()`.
     * Kolizja z kluczem rdzenia staje się przez to niemożliwa z konstrukcji.
     */
    public function testModuleCatalogueAcceptsOnlyItsOwnPrefix(): void
    {
        $source = self::temporaryCatalogue([
            'module.probny.name' => 'Nazwa modułu',
            'settings.tab.appearance' => 'PODMIENIONE',
            'zupelnie.obcy.klucz' => 'obcy',
        ]);

        $catalog = Catalog::load(Language::FALLBACK, self::catalogDirectory(), ['probny' => $source]);

        self::assertNotNull($catalog);
        self::assertSame('Nazwa modułu', $catalog->text('module.probny.name'));
        self::assertNotSame('PODMIENIONE', $catalog->text('settings.tab.appearance'), 'rdzeń jest nietykalny');
        self::assertSame(
            ['settings.tab.appearance', 'zupelnie.obcy.klucz'],
            $catalog->ignored(),
        );

        self::removeCatalogue($source);
    }

    /** Moduł bez pliku dla danego języka nie jest błędem — katalog po prostu go pomija. */
    public function testMissingModuleCatalogueIsNotAnError(): void
    {
        $catalog = Catalog::load(Language::FALLBACK, self::catalogDirectory(), ['widmo' => '/nie-ma-takiego']);

        self::assertNotNull($catalog);
        self::assertSame([], $catalog->ignored());
    }

    /**
     * Test kompletności języków obejmuje **pliki modułów** tak samo, jak
     * rdzeniowe: moduł, który przetłumaczył się tylko na jeden język, jest tym
     * samym błędem, co rdzeń, który to zrobił.
     */
    #[DataProvider('moduleCatalogues')]
    public function testEveryModuleCarriesTheSameKeysInEveryLanguage(string $id, string $directory): void
    {
        $reference = Catalog::load(Language::FALLBACK, $directory);

        self::assertNotNull($reference, 'moduł ' . $id . ' ma plik języka zapasowego');

        foreach ($reference->keys() as $key) {
            self::assertStringStartsWith('module.' . $id . '.', $key);
        }

        foreach (Language::catalogued() as $language) {
            $catalog = Catalog::load($language, $directory);

            self::assertNotNull($catalog, 'moduł ' . $id . ' ma plik dla języka ' . $language->value);
            self::assertSame($reference->keys(), $catalog->keys(), 'moduł ' . $id . ', język ' . $language->value);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function moduleCatalogues(): array
    {
        $root = dirname(__DIR__, 3) . '/src/Module';
        $cases = [];

        foreach ((array) glob($root . '/*/lang', GLOB_ONLYDIR) as $directory) {
            if (!is_string($directory)) {
                continue;
            }

            // Katalog modułu nazywa się jak jego klasa; identyfikator wyprowadzamy
            // z pliku języka zapasowego, bo to on jest źródłem prawdy o przedrostku.
            $catalog = Catalog::load(Language::FALLBACK, $directory);
            $keys = $catalog?->keys() ?? [];
            $first = $keys[0] ?? '';
            $parts = explode('.', $first);
            $id = $parts[1] ?? '';

            $cases[basename(dirname($directory))] = [$id, $directory];
        }

        return $cases;
    }

    /** @param array<string, string> $entries */
    private static function temporaryCatalogue(array $entries): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'light-manager-module-lang-' . uniqid();

        mkdir($directory, 0o700, true);
        file_put_contents(
            $directory . DIRECTORY_SEPARATOR . Language::FALLBACK->value . '.php',
            '<?php return ' . var_export($entries, true) . ';',
        );

        return $directory;
    }

    private static function removeCatalogue(string $directory): void
    {
        @unlink($directory . DIRECTORY_SEPARATOR . Language::FALLBACK->value . '.php');
        @rmdir($directory);
    }

    public function testTranslationSubstitutesNamedParameters(): void
    {
        $this->pinLanguage(Language::Polish);

        self::assertSame(
            'Nie ma modułu „drzewo” — otwarto przeglądarkę plików.',
            TranslatorService::getInstance()->translate('module.startup.unknown', ['module' => 'drzewo']),
        );
    }

    public function testUnknownKeyComesBackAsItself(): void
    {
        $this->pinLanguage(Language::English);

        self::assertSame('nie.ma.takiego.klucza', TranslatorService::getInstance()->translate('nie.ma.takiego.klucza'));
    }

    /** `auto` nie ma i nie będzie miał własnego pliku — a mimo to nic się nie wywraca. */
    public function testLanguageWithoutACatalogueIsNotAnError(): void
    {
        self::assertNull(Catalog::load(Language::Auto, self::catalogDirectory()));
    }

    /** @return array<string, array{int, string}> */
    public static function rejectedCounts(): array
    {
        return [
            'jeden klucz' => [1, 'wartość spoza zakresu, użyto domyślnej'],
            'dwa klucze' => [2, 'wartości spoza zakresu, użyto domyślnych'],
            'pięć kluczy' => [5, 'wartości spoza zakresu, użyto domyślnych'],
        ];
    }

    #[DataProvider('rejectedCounts')]
    public function testPolishPicksTheFormThatMatchesTheCount(int $count, string $expected): void
    {
        $this->pinLanguage(Language::Polish);

        $message = TranslatorService::getInstance()->plural('config.rejected', $count, ['keys' => 'Motyw']);

        self::assertStringContainsString($expected, $message);
        self::assertStringContainsString('Motyw', $message);
    }

    public function testEnglishPicksTheSingularOnlyForOne(): void
    {
        $this->pinLanguage(Language::English);

        $translator = TranslatorService::getInstance();

        self::assertStringContainsString('value out of range', $translator->plural('config.rejected', 1, ['keys' => 'Theme']));
        self::assertStringContainsString('values out of range', $translator->plural('config.rejected', 3, ['keys' => 'Theme']));
    }

    /** @return array<string, array{Language, string}> */
    public static function decimalSeparators(): array
    {
        return [
            'polski — przecinek' => [Language::Polish, '1,2'],
            'angielski — kropka' => [Language::English, '1.2'],
        ];
    }

    #[DataProvider('decimalSeparators')]
    public function testNumbersFollowTheLanguage(Language $language, string $expected): void
    {
        $this->pinLanguage($language);

        self::assertSame($expected, TranslatorService::getInstance()->number(1.234, 1));
    }

    /** Rozmiary plików nie dochodzą do czterech cyfr, ale gdyby doszły — bez separatora tysięcy. */
    public function testThousandsAreNotGrouped(): void
    {
        $this->pinLanguage(Language::Polish);

        self::assertSame('1024', TranslatorService::getInstance()->number(1024.0));
    }

    public function testEnvironmentDecidesWhenTheSettingSaysAuto(): void
    {
        $this->pinLanguage(Language::Polish);

        self::assertSame(Language::Polish, TranslatorService::getInstance()->active());
    }

    public function testUnrecognisedEnvironmentFallsBackToEnglish(): void
    {
        $this->pinLanguage(Language::Polish);

        putenv('LC_ALL=C');
        $this->forgetLanguageServices();

        self::assertSame(Language::English, TranslatorService::getInstance()->active());
    }

    /** Wybór zapisany w konfiguracji jest mocniejszy od środowiska — na tym polega wybór. */
    public function testConfiguredLanguageOutranksTheEnvironment(): void
    {
        $this->pinLanguage(Language::English);

        mkdir($this->pinnedHome() . '/.light-manager', 0o700, true);
        file_put_contents($this->pinnedHome() . '/.light-manager/settings.json', '{"language": "pl"}');
        $this->forgetLanguageServices();

        self::assertSame(Language::Polish, TranslatorService::getInstance()->active());
    }
}
