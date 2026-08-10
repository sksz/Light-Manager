<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Rendering;

use LightManager\Application\Dto\Settings;
use LightManager\Infrastructure\Config\SettingsService;
use LightManager\Infrastructure\Rendering\ThemeService;
use LightManager\Tests\Support\ResetsSingletons;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThemeServiceTest extends TestCase
{
    use ResetsSingletons;

    private string $home;

    private string|false $previousHome;

    private ThemeService $themes;

    protected function setUp(): void
    {
        // Motyw aktywny czyta konfigurację, a ta czyta katalog domowy — bez
        // podmiany test zależałby od ustawień osoby, która go uruchamia.
        $this->previousHome = getenv('HOME');
        $this->home = sys_get_temp_dir() . '/light-manager-theme-' . uniqid();

        mkdir($this->home, 0o700, true);
        putenv('HOME=' . $this->home);

        $this->resetSingleton(SettingsService::class);
        $this->resetSingleton(ThemeService::class);

        $this->themes = ThemeService::getInstance();
    }

    protected function tearDown(): void
    {
        putenv($this->previousHome === false ? 'HOME' : 'HOME=' . $this->previousHome);

        // Zapis w jednym z testów tworzy plik konfiguracji — katalog zniknie
        // tylko wtedy, gdy sprzątanie idzie od środka.
        @unlink($this->home . '/.light-manager/settings.json');
        @rmdir($this->home . '/.light-manager');
        @rmdir($this->home);

        $this->resetSingleton(SettingsService::class);
        $this->resetSingleton(ThemeService::class);
    }

    public function testCatalogHoldsTheFourPalettesFromTheComparison(): void
    {
        self::assertSame(['grafit', 'nordyk', 'papier', 'indygo'], $this->themes->names());
    }

    /** @return array<string, array{string, bool}> */
    public static function themeNames(): array
    {
        return [
            'grafit' => ['grafit', true],
            'nordyk' => ['nordyk', true],
            'papier' => ['papier', true],
            'indygo' => ['indygo', true],
            'nazwa spoza katalogu' => ['nieistniejacy', false],
            'pusta nazwa' => ['', false],
        ];
    }

    #[DataProvider('themeNames')]
    public function testKnowsWhichNamesItServes(string $name, bool $known): void
    {
        self::assertSame($known, $this->themes->has($name));
    }

    /** Każda paleta ma własne wartości — inaczej przełącznik niczego by nie zmieniał. */
    public function testEveryPaletteDiffersFromTheOthers(): void
    {
        $backgrounds = [];

        foreach ($this->themes->names() as $name) {
            $backgrounds[] = $this->themes->named($name)->background;
        }

        self::assertSame($backgrounds, array_unique($backgrounds));
    }

    /** Nazwa wpisana ręcznie do pliku nie ma prawa wywrócić rysowania. */
    public function testUnknownNameFallsBackToTheDefault(): void
    {
        self::assertSame($this->themes->named('grafit'), $this->themes->named('nieistniejacy'));
    }

    public function testActiveThemeFollowsTheConfiguration(): void
    {
        self::assertSame($this->themes->named('grafit'), $this->themes->active());

        SettingsService::getInstance()->save((new Settings())->withTheme('indygo'));

        // Bez restartu i bez budowania usługi od nowa — tak działa podgląd na żywo.
        self::assertSame($this->themes->named('indygo'), $this->themes->active());
    }
}
