<?php

declare(strict_types=1);

namespace LightManager\Tests\Documentation;

use LightManager\Application\Dto\Language;
use LightManager\Application\Dto\SettingKey;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleSetting;
use LightManager\Application\Module\ProvidesSettingsTab;
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
 * **Spis ustawień w podręczniku jest kopią stanu kodu** (krok 66).
 *
 * Pozycja dołożona do modułu bez wiersza w podręczniku i wiersz opisujący
 * pozycję, której już nie ma, są **tym samym błędem** — a oba są niewidoczne,
 * bo tabela wygląda tak samo poprawnie w dniu, w którym przestaje być prawdą.
 *
 * Test porównuje **dwie rzeczy z trzech kolumn**: nazwę pozycji i wartość
 * domyślną. Kolumna „Wartości" zostaje człowiekowi i jest to rozstrzygnięcie,
 * nie przeoczenie: osiemdziesiąt jeden przystanków suwaka zapisuje się
 * w podręczniku jako `20–80`, a `0, 10, …, 100` jako `0–100 co 10` — i tak ma
 * być, bo tabela jest dla czytelnika, a nie dla maszyny. Maszyna pilnuje tego,
 * co da się porównać bez zgadywania.
 *
 * Wartość domyślna sprawdza się **zawieraniem**, a nie równością, z tego samego
 * powodu: `Automatyczny (auto)` niesie i nazwę z ekranu, i wartość z pliku
 * konfiguracyjnego, a obie formy są w podręczniku potrzebne.
 *
 * Ustawienia rdzenia porównuje się **zbiorem**, a modułów — **kolejnością**:
 * tabela rdzenia idzie wedle zakładek ekranu (Wygląd, Grafika, Zasoby, Moduły),
 * a nie wedle kolejności w enumie, natomiast zakładka modułu rysuje pozycje
 * dokładnie w kolejności deklaracji.
 */
final class DocumentedSettingsMatchTest extends TestCase
{
    /** Wiersz, który opisuje nie pozycję, lecz zwyczaj — cały w kursywie. */
    private const PROSE_ROW = '/^\*\(.*\)\*$/u';

    private static ?ScreenFixture $app = null;

    #[DataProvider('languages')]
    public function testCoreSettingsMatchTheDocumentedList(string $code, string $document): void
    {
        $language = Language::from($code);
        $catalog = self::catalogue($language);
        $rows = self::list($document, 'ustawienia:rdzen');

        $documented = [];

        foreach ($rows as $row) {
            $position = DocumentationTree::plain($row[1]);

            if (preg_match(self::PROSE_ROW, trim($row[1])) === 1) {
                continue;
            }

            $documented[$position] = DocumentationTree::plain($row[3]);
        }

        $expected = [];
        $defaults = new Settings();

        foreach (SettingKey::cases() as $key) {
            $expected[DocumentedCatalogues::text($catalog, $key->labelKey())] = $defaults->{$key->value};
        }

        self::assertSame(
            array_keys($expected),
            self::sortedLike(array_keys($expected), array_keys($documented)),
            $document . ' — spis ustawień rdzenia rozjechał się z SettingKey',
        );

        foreach ($expected as $label => $value) {
            self::assertStringContainsStringIgnoringCase(
                self::form($value, $language),
                $documented[$label] ?? '',
                $document . ' — wartość domyślna pozycji „' . $label . '"',
            );
        }
    }

    #[DataProvider('moduleTabs')]
    public function testModuleSettingsMatchTheDocumentedList(string $code, string $document, string $module): void
    {
        $language = Language::from($code);
        $catalog = self::catalogue($language);
        $rows = self::list($document, 'ustawienia:' . $module);
        $settings = self::settingsOf($module);

        self::assertSame(
            array_map(
                static fn (ModuleSetting $setting): string => DocumentedCatalogues::text($catalog, $setting->labelKey),
                $settings,
            ),
            array_map(static fn (array $row): string => DocumentationTree::plain($row[0]), $rows),
            $document . ' — spis ustawień modułu ' . $module . ' rozjechał się z deklaracją',
        );

        foreach ($settings as $index => $setting) {
            self::assertStringContainsStringIgnoringCase(
                self::form($setting->default, $language),
                DocumentationTree::plain($rows[$index][2]),
                $document . ' — wartość domyślna pozycji ' . $module . '.' . $setting->key,
            );
        }
    }

    /** Moduł z zakładką ma spis w obu językach, a moduł bez zakładki — nie ma go wcale. */
    public function testEveryModuleWithATabHasAMarkedList(): void
    {
        $marked = [];

        foreach (array_keys(DocumentationTree::allLists()) as $name) {
            if (str_starts_with($name, 'ustawienia:') && $name !== 'ustawienia:rdzen') {
                $marked[] = substr($name, strlen('ustawienia:'));
            }
        }

        sort($marked);

        $expected = [];

        foreach (self::app()->modules->accepted() as $module) {
            if ($module instanceof ProvidesSettingsTab) {
                $expected[] = $module->id();
            }
        }

        sort($expected);

        self::assertSame($expected, $marked, 'spisy ustawień modułów rozjechały się z modułami wnoszącymi zakładkę');
    }

    /** @return iterable<string, array{string, string}> */
    public static function languages(): iterable
    {
        yield 'pl' => ['pl', 'docs/pl/podrecznik/06-ustawienia.md'];
        yield 'en' => ['en', 'docs/en/manual/06-settings.md'];
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function moduleTabs(): iterable
    {
        foreach (self::languages() as [$code, $document]) {
            foreach (DocumentationTree::lists($document) as $name => $list) {
                if (str_starts_with($name, 'ustawienia:') && $name !== 'ustawienia:rdzen') {
                    $module = substr($name, strlen('ustawienia:'));

                    yield $code . ': ' . $module => [$code, $document, $module];
                }
            }
        }
    }

    /**
     * Postać, w jakiej wartość domyślna ma stać w podręczniku.
     *
     * @param bool|int|string|array<string, array<string, bool|int|string>> $value
     */
    private static function form(bool|int|string|array $value, Language $language): string
    {
        if (is_array($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $language === Language::Polish
                ? ($value ? 'tak' : 'nie')
                : ($value ? 'yes' : 'no');
        }

        if ($value === '') {
            return $language === Language::Polish ? 'puste' : 'empty';
        }

        return (string) $value;
    }

    /**
     * Kolejność wzorcowa nałożona na zbiór dokumentacji — żeby różnica pokazała
     * **brak i nadmiar**, a nie przestawienie wierszy.
     *
     * @param list<string> $pattern
     * @param list<string> $actual
     *
     * @return list<string>
     */
    private static function sortedLike(array $pattern, array $actual): array
    {
        $known = array_values(array_filter($pattern, static fn (string $label): bool => in_array($label, $actual, true)));
        $extra = array_values(array_diff($actual, $pattern));

        return [...$known, ...$extra];
    }

    /** @return list<list<string>> */
    private static function list(string $document, string $name): array
    {
        $lists = DocumentationTree::lists($document);

        self::assertArrayHasKey($name, $lists, $document . ' — brak spisu ' . $name);

        return $lists[$name]['rows'];
    }

    /** @return list<ModuleSetting> */
    private static function settingsOf(string $id): array
    {
        foreach (self::app()->modules->accepted() as $module) {
            if ($module->id() === $id && $module instanceof ProvidesSettingsTab) {
                return $module->settingsTab()->settings;
            }
        }

        self::fail('moduł ' . $id . ' nie wnosi zakładki ustawień');
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
