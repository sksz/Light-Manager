<?php

declare(strict_types=1);

namespace LightManager\Tests\Documentation;

use LightManager\Application\Command\CommandArgument;
use LightManager\Application\Dto\Language;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Infrastructure\I18n\Catalog;
use LightManager\Module\Browser\Domain\ValueObject\DirectoryPath;
use LightManager\Module\Browser\Domain\ValueObject\Entry;
use LightManager\Tests\Support\DocumentationTree;
use LightManager\Tests\Support\DocumentedCatalogues;
use LightManager\Tests\Support\InMemoryDirectoryRepository;
use LightManager\Tests\Support\ScreenFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * **Spisy komend, kwerend i modułów są kopią rejestrów** (krok 66).
 *
 * Miara kroku, sprawdzana wprost: *dopisanie kwerendy bez wiersza
 * w dokumentacji czerwieni bramkę*. Rejestr jest przy tym **jedynym** źródłem —
 * ten sam, którym aplikacja komendy wykonuje, a kwerendy zadaje. Spis pisany
 * z głowy rozjechałby się w pierwszym kroku planu po tym.
 *
 * Do kroku 66 spisu komend i kwerend w dokumentacji **nie było** i był to wybór,
 * nie przeoczenie: podręcznik odsyłał po nie do okna `F12`, bo tam powstają
 * z rejestru i nie mają jak skłamać. Krok 66 rozstrzygnął to inaczej i wraz
 * z powodem ([00-decyzje.md](../../docs/plans/00-decyzje.md), D112): spis, do
 * którego zagląda się **przy pisaniu kodu**, ma stać w przewodniku dewelopera,
 * a nie wyłącznie w uruchomionej aplikacji — a skoro ma stać, to ma być
 * pilnowany.
 *
 * Trzy kolumny są porównywane w całości: **nazwa**, **argumenty** wraz
 * z wymagalnością i **opis** wzięty z katalogu napisów tego języka. Nazwa
 * modułu też idzie przez katalog — bo to ona stoi na ekranie, a nie
 * identyfikator.
 */
final class DocumentedRegistriesMatchTest extends TestCase
{
    private static ?ScreenFixture $app = null;

    #[DataProvider('languages')]
    public function testCommandListMatchesTheRegistry(string $code, string $document): void
    {
        $catalog = self::catalogue(Language::from($code));
        $expected = [];

        foreach (self::app()->commandRegistry->all() as $command) {
            $expected[] = [
                $command->name(),
                self::arguments($command->arguments(), $catalog),
                DocumentedCatalogues::text($catalog, $command->descriptionKey()),
            ];
        }

        self::assertSame(
            $expected,
            self::rowsOf($document, 'komendy'),
            $document . ' — spis komend rozjechał się z rejestrem',
        );
    }

    #[DataProvider('languages')]
    public function testQueryListMatchesTheRegistry(string $code, string $document): void
    {
        $catalog = self::catalogue(Language::from($code));
        $expected = [];

        foreach (self::app()->state->queries()->all() as $query) {
            $expected[] = [
                $query->name(),
                self::arguments($query->arguments(), $catalog),
                DocumentedCatalogues::text($catalog, $query->descriptionKey()),
            ];
        }

        self::assertSame(
            $expected,
            self::rowsOf($document, 'kwerendy'),
            $document . ' — spis kwerend rozjechał się z rejestrem',
        );
    }

    /**
     * Spis modułów porównuje się **zbiorem**, nie kolejnością: podręcznik
     * grupuje moduły wedle tego, czego wymagają od maszyny, a `Bootstrap` —
     * wedle kolejności deklaracji. Obie kolejności są celowe i żadna nie jest
     * kopią drugiej.
     */
    #[DataProvider('moduleLists')]
    public function testModuleListMatchesTheRegistry(string $code, string $document): void
    {
        $catalog = self::catalogue(Language::from($code));
        $expected = [];

        foreach (self::app()->modules->declared() as $module) {
            $shortcut = $module->shortcut();
            $expected[DocumentedCatalogues::text($catalog, $module->nameKey())] = $shortcut === null
                ? '—'
                : 'Ctrl+' . mb_strtoupper($shortcut->character);
        }

        $documented = [];

        foreach (self::rowsOf($document, 'moduly') as $row) {
            $documented[$row[0]] = $row[1];
        }

        ksort($expected);
        ksort($documented);

        self::assertSame($expected, $documented, $document . ' — spis modułów rozjechał się z rejestrem');
    }

    /**
     * **Zestaw testowy zna te same moduły, co `Bootstrap`** — inaczej spisy
     * pilnowałyby aplikacji, której nie ma.
     *
     * Bez tego sprawdzenia miara kroku ma dziurę: moduł dopisany do
     * `Bootstrapu`, a nieznany zestawowi, nie wnosi do rejestrów niczego, co
     * test mógłby porównać ze spisem — więc kwerenda **bez wiersza
     * w dokumentacji** przechodziłaby przez zieloną bramkę. Wykryte próbą przy
     * domykaniu kroku 66, a nie rozumowaniem.
     */
    public function testTheFixtureKnowsTheSameModulesAsBootstrap(): void
    {
        $source = DocumentationTree::root() . '/src/Presentation/Cli/Bootstrap.php';

        preg_match_all('/new (\w+Module)\(/', (string) file_get_contents($source), $matched);

        $declared = array_values(array_unique($matched[1]));
        sort($declared);

        $known = array_map(
            static fn (ModuleInterface $module): string => (new \ReflectionClass($module))->getShortName(),
            self::app()->modules->declared(),
        );

        sort($known);

        self::assertSame($declared, $known, 'ScreenFixture rozjechał się ze spisem modułów w Bootstrapie');
    }

    /** @return iterable<string, array{string, string}> */
    public static function languages(): iterable
    {
        yield 'pl' => ['pl', 'docs/pl/przewodnik/08-spisy.md'];
        yield 'en' => ['en', 'docs/en/guide/08-lists.md'];
    }

    /** @return iterable<string, array{string, string}> */
    public static function moduleLists(): iterable
    {
        yield 'pl' => ['pl', 'docs/pl/podrecznik/05-moduly.md'];
        yield 'en' => ['en', 'docs/en/manual/05-modules.md'];
    }

    /**
     * Argumenty w postaci, w jakiej stoją w spisie: wymagany w nawiasach
     * ostrych, opcjonalny w kwadratowych, brak — półpauzą.
     *
     * @param list<CommandArgument> $arguments
     */
    private static function arguments(array $arguments, Catalog $catalog): string
    {
        if ($arguments === []) {
            return '—';
        }

        return implode(' ', array_map(
            static fn (CommandArgument $argument): string => $argument->required
                ? '<' . DocumentedCatalogues::text($catalog, $argument->labelKey) . '>'
                : '[' . DocumentedCatalogues::text($catalog, $argument->labelKey) . ']',
            $arguments,
        ));
    }

    /**
     * Wiersze spisu, oczyszczone z ozdobników markdowna.
     *
     * @return list<list<string>>
     */
    private static function rowsOf(string $document, string $name): array
    {
        $lists = DocumentationTree::lists($document);

        self::assertArrayHasKey($name, $lists, $document . ' — brak spisu ' . $name);

        return array_map(
            static fn (array $row): array => array_map(DocumentationTree::plain(...), $row),
            $lists[$name]['rows'],
        );
    }

    private static function catalogue(Language $language): Catalog
    {
        /** @var list<ModuleInterface> $modules */
        $modules = self::app()->modules->accepted();

        return DocumentedCatalogues::of($language, $modules);
    }

    private static function app(): ScreenFixture
    {
        if (self::$app === null) {
            $directories = (new InMemoryDirectoryRepository())->add('/home', [Entry::file('notatka.txt', 12)]);
            self::$app = new ScreenFixture($directories->get(new DirectoryPath('/home'), false), $directories);
        }

        return self::$app;
    }
}
