<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Config;

use LightManager\Application\Dto\Language;
use LightManager\Application\Dto\Settings;
use LightManager\Infrastructure\Config\SettingsService;
use LightManager\Tests\Support\PinsLanguage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Jedyny test w projekcie, który naprawdę pisze po dysku — bo cała rzecz tego
 * kroku polega na tym, że ustawienie przeżywa proces.
 *
 * Katalog domowy jest podmieniany na tymczasowy, więc test nie ma jak dotknąć
 * konfiguracji osoby, która go uruchamia. Język jest przypięty do polskiego,
 * bo komunikaty o wadliwym pliku idą od kroku 15 przez katalog napisów — bez
 * tego wynik zależałby od `LANG` w środowisku uruchomienia.
 */
final class SettingsServiceTest extends TestCase
{
    use PinsLanguage;

    /** @var list<string> */
    private const THEMES = ['grafit', 'nordyk', 'papier', 'indygo'];

    private string $home;

    protected function setUp(): void
    {
        $this->pinLanguage(Language::Polish);

        $this->home = $this->pinnedHome();
    }

    protected function tearDown(): void
    {
        $this->removeHome();
        $this->unpinLanguage();
    }

    public function testStartWithoutFileGivesDefaultsInSilence(): void
    {
        $loaded = SettingsService::getInstance()->load(self::THEMES);

        self::assertTrue($loaded->settings->equals(new Settings()));
        self::assertNull($loaded->problem);
    }

    /** Sam start niczego nie tworzy — użytkownik, który nie wszedł w ustawienia, nie zostawia śladów. */
    public function testLoadingCreatesNothingOnDisk(): void
    {
        SettingsService::getInstance()->load(self::THEMES);

        self::assertDirectoryDoesNotExist($this->home . '/.light-manager');
    }

    public function testSavedSettingsSurviveANewProcess(): void
    {
        $problem = SettingsService::getInstance()->save((new Settings())->withTheme('indygo')->withPaletteColors(128));

        self::assertNull($problem);
        self::assertFileExists($this->path());

        // Nowa instancja to najbliższy odpowiednik ponownego uruchomienia.
        $this->resetSingleton(SettingsService::class);
        $reloaded = SettingsService::getInstance()->load(self::THEMES)->settings;

        self::assertSame('indygo', $reloaded->theme);
        self::assertSame(128, $reloaded->paletteColors);
    }

    public function testSaveLeavesNoTemporaryFileBehind(): void
    {
        SettingsService::getInstance()->save(new Settings());

        $left = glob($this->home . '/.light-manager/.settings-*');

        self::assertSame([], $left === false ? [] : $left);
    }

    public function testBrokenFileDoesNotStopTheStartAndIsNotOverwritten(): void
    {
        $this->write('{ to nie jest JSON');

        $loaded = SettingsService::getInstance()->load(self::THEMES);

        self::assertTrue($loaded->settings->equals(new Settings()));
        self::assertNotNull($loaded->problem);
        self::assertSame('{ to nie jest JSON', file_get_contents($this->path()));
    }

    /**
     * Klucz `showHiddenEntries` zszedł w kroku 21 do modułu przeglądarki, ale plik
     * sprzed tej wersji nie ma się przez to cofać do ustawienia domyślnego.
     * Przepisujemy go **raz**, przy odczycie.
     */
    public function testHiddenEntriesFromAnOlderFileMoveIntoTheBrowserModule(): void
    {
        $this->write('{"theme": "papier", "showHiddenEntries": true}');

        $loaded = SettingsService::getInstance()->load(self::THEMES);

        self::assertTrue($loaded->settings->moduleValue('browser', 'showHidden'));
        self::assertNull($loaded->problem, 'przepisanie nie jest problemem, o którym trzeba mówić');
    }

    /** Wartość zapisana już przez moduł wygrywa ze starym kluczem rdzenia. */
    public function testTheModuleValueWinsOverTheLegacyKey(): void
    {
        $this->write('{"showHiddenEntries": true, "modules": {"browser": {"showHidden": false}}}');

        $loaded = SettingsService::getInstance()->load(self::THEMES);

        self::assertFalse($loaded->settings->moduleValue('browser', 'showHidden'));
    }

    /** Stary klucz wypada z pliku przy pierwszym zapisie — nikt go już nie wypisuje. */
    public function testTheLegacyKeyDisappearsOnTheNextSave(): void
    {
        $this->write('{"showHiddenEntries": true}');

        $settings = SettingsService::getInstance()->load(self::THEMES)->settings;
        SettingsService::getInstance()->save($settings);

        $written = (string) file_get_contents($this->path());

        self::assertStringNotContainsString('showHiddenEntries', $written);
        self::assertStringContainsString('showHidden', $written, 'ustawienie zostaje, tylko w innym miejscu');
    }

    /** Moduł domyślny to zwykły klucz rdzenia — zapisywany i odczytywany jak reszta. */
    public function testStartupModuleSurvivesANewProcess(): void
    {
        SettingsService::getInstance()->save((new Settings())->withStartupModule('file-info'));

        $this->resetSingleton(SettingsService::class);

        self::assertSame('file-info', SettingsService::getInstance()->load(self::THEMES)->settings->startupModule);
    }

    public function testUnknownKeyIsIgnoredWithoutAWord(): void
    {
        $this->write('{"theme": "papier", "czegoTakiegoNieMa": 7}');

        $loaded = SettingsService::getInstance()->load(self::THEMES);

        self::assertSame('papier', $loaded->settings->theme);
        self::assertNull($loaded->problem);
    }

    /** @return array<string, array{string, string}> */
    public static function outOfRangeValues(): array
    {
        return [
            'motyw spoza katalogu' => ['{"theme": "nieistniejacy"}', 'Motyw'],
            'paleta spoza listy' => ['{"paletteColors": 7}', 'Kolory palety Sixela'],
            'przełącznik nie-logiczny' => ['{"textAntialias": "tak"}', 'Wygładzanie tekstu'],
            'nieznany język' => ['{"language": "kaszubski"}', 'Język'],
        ];
    }

    #[DataProvider('outOfRangeValues')]
    public function testOutOfRangeValueFallsBackAndSaysSo(string $json, string $expectedLabel): void
    {
        $this->write($json);

        $loaded = SettingsService::getInstance()->load(self::THEMES);

        self::assertTrue($loaded->settings->equals(new Settings()));
        self::assertStringContainsString($expectedLabel, (string) $loaded->problem);
    }

    /** Wartość odrzucona nie ma prawa pociągnąć za sobą pozostałych kluczy. */
    public function testGoodKeysSurviveABadNeighbour(): void
    {
        $this->write('{"theme": "nieistniejacy", "paletteColors": 128}');

        $loaded = SettingsService::getInstance()->load(self::THEMES);

        self::assertSame('grafit', $loaded->settings->theme);
        self::assertSame(128, $loaded->settings->paletteColors);
    }

    public function testSaveClearsThePreviousWarning(): void
    {
        $this->write('{"theme": "nieistniejacy"}');

        $settings = SettingsService::getInstance();
        $settings->load(self::THEMES);
        $settings->save((new Settings())->withTheme('nordyk'));

        self::assertNull($settings->load(self::THEMES)->problem);
    }

    /** Więcej niż jeden odrzucony klucz zmienia zdanie, nie tylko wyliczenie. */
    public function testSeveralRejectedKeysGetThePluralSentence(): void
    {
        $this->write('{"theme": "nieistniejacy", "paletteColors": 7}');

        $problem = (string) SettingsService::getInstance()->load(self::THEMES)->problem;

        self::assertStringContainsString('wartości spoza zakresu', $problem);
        self::assertStringContainsString('Motyw', $problem);
        self::assertStringContainsString('Kolory palety Sixela', $problem);
    }

    public function testLanguageSurvivesTheRoundTrip(): void
    {
        SettingsService::getInstance()->save((new Settings())->withLanguage('en'));

        $this->forgetLanguageServices();

        self::assertSame('en', SettingsService::getInstance()->load(self::THEMES)->settings->language);
    }

    public function testModuleSettingsSurviveANewProcess(): void
    {
        SettingsService::getInstance()->save(
            (new Settings())->withModuleValue('file-info', 'timeout', 5),
        );

        $this->resetSingleton(SettingsService::class);
        $reloaded = SettingsService::getInstance()->load(self::THEMES)->settings;

        self::assertSame(5, $reloaded->moduleValue('file-info', 'timeout'));
    }

    /**
     * Moduł chwilowo wyłączony — albo usunięty z listy w `Bootstrap` — nie ma
     * tracić swojej konfiguracji. Rdzeń nie zna deklaracji cudzych pozycji, więc
     * jedyne, co może zrobić, to ich nie ruszać.
     */
    public function testSettingsOfAnUnknownModuleStayUntouched(): void
    {
        $this->write('{"theme": "papier", "modules": {"nieznany": {"klucz": "wartość", "liczba": 3}}}');

        $settings = SettingsService::getInstance()->load(self::THEMES);
        SettingsService::getInstance()->save($settings->settings->withTheme('nordyk'));

        $written = (string) file_get_contents($this->path());

        self::assertStringContainsString('nieznany', $written);
        self::assertStringContainsString('klucz', $written);
        self::assertSame(3, SettingsService::getInstance()->current()->moduleValue('nieznany', 'liczba'));
        self::assertSame('wartość', SettingsService::getInstance()->current()->moduleValue('nieznany', 'klucz'));
    }

    /** Wartość nieskalarna w podprzestrzeni modułu odpada bez słowa — jak nieznany klucz rdzenia. */
    public function testNonScalarModuleValueIsDroppedInSilence(): void
    {
        $this->write('{"modules": {"alfa": {"dobra": 1, "zla": {"zagnieżdżona": true}}}}');

        $loaded = SettingsService::getInstance()->load(self::THEMES);

        self::assertSame(1, $loaded->settings->moduleValue('alfa', 'dobra'));
        self::assertNull($loaded->settings->moduleValue('alfa', 'zla'));
        self::assertNull($loaded->problem, 'to nie jest wartość spoza zakresu, tylko śmieć w pliku');
    }

    /** Plik użytkownika, który nie ruszył żadnego modułu, nie nosi pustego podobiektu. */
    public function testEmptyModuleSubspaceIsNotWritten(): void
    {
        SettingsService::getInstance()->save(new Settings());

        self::assertStringNotContainsString('modules', (string) file_get_contents($this->path()));
    }

    public function testLocationPointsAtTheHiddenDirectoryInHome(): void
    {
        self::assertSame($this->path(), SettingsService::getInstance()->location());
    }

    private function path(): string
    {
        return $this->home . '/.light-manager/settings.json';
    }

    private function write(string $contents): void
    {
        mkdir($this->home . '/.light-manager', 0o700, true);
        file_put_contents($this->path(), $contents);
    }

    private function removeHome(): void
    {
        foreach (['/.light-manager/settings.json', '/.light-manager', ''] as $suffix) {
            $path = $this->home . $suffix;

            if (is_file($path)) {
                @unlink($path);
            } elseif (is_dir($path)) {
                @rmdir($path);
            }
        }
    }
}
