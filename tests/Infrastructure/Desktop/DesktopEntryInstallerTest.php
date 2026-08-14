<?php

declare(strict_types=1);

namespace LightManager\Tests\Infrastructure\Desktop;

use Imagick;
use LightManager\Infrastructure\Desktop\DesktopEntryInstaller;
use LightManager\Infrastructure\Glfw\GlfwWindowService;
use LightManager\Infrastructure\Rendering\Theme;
use PHPUnit\Framework\TestCase;

/**
 * Ikona okna i wpis pulpitu (krok 37).
 *
 * Instalacja idzie w katalog tymczasowy, więc test nie ma jak dotknąć pulpitu
 * osoby, która go uruchamia — tą samą zasadą, co `SettingsServiceTest`.
 *
 * Najważniejsze zdanie tego testu jest jednowierszowe i pilnuje pary, bez której
 * cała droga do ikony przestaje działać: `StartupWMClass` wpisu musi się zgadzać
 * z klasą, którą okno podaje o sobie X11 (`WM_CLASS`). Rozejście się ich znaczy
 * ikonę w spisie programów i ikonę zastępczą na pasku zadań.
 */
final class DesktopEntryInstallerTest extends TestCase
{
    private string $home;

    protected function setUp(): void
    {
        if (!extension_loaded('imagick')) {
            self::markTestSkipped('ikona rysuje się Imagickiem');
        }

        $this->home = sys_get_temp_dir() . '/lm-desktop-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->home)) {
            self::removeDirectory($this->home);
        }
    }

    public function testInstallWritesEveryIconSizeAndTheEntry(): void
    {
        $written = $this->installer()->install();

        self::assertCount(5, $written, 'cztery rozmiary ikony i wpis');

        foreach ($written as $path) {
            self::assertFileExists($path);
        }

        self::assertStringEndsWith('/applications/light-manager.desktop', $written[4]);
    }

    public function testIconIsASquarePngOfTheDeclaredSize(): void
    {
        $this->installer()->install();

        $icon = new Imagick($this->home . '/.local/share/icons/hicolor/256x256/apps/light-manager.png');

        self::assertSame('PNG', $icon->getImageFormat());
        self::assertSame(256, $icon->getImageWidth());
        self::assertSame(256, $icon->getImageHeight());

        $icon->clear();
    }

    /** Ikona bierze kolory z **włączonego** motywu — inny motyw, inna ikona. */
    public function testIconUsesTheColoursOfTheGivenTheme(): void
    {
        $this->installer()->install();

        $icon = new Imagick($this->home . '/.local/share/icons/hicolor/256x256/apps/light-manager.png');
        // Drugi wiersz listy jest zaznaczeniem i rysuje się akcentem motywu;
        // pierwszy — zwykłą treścią, czyli rolą przygaszoną.
        $selected = $icon->getImagePixelColor(128, 108)->getColorAsString();
        $ordinary = $icon->getImagePixelColor(128, 66)->getColorAsString();
        $icon->clear();

        self::assertStringContainsString('217,164,65', $selected, 'akcent Grafitu na zaznaczonym wierszu');
        self::assertStringContainsString('141,147,157', $ordinary, 'rola przygaszona na pozostałych');
    }

    /**
     * Para, na której stoi cała droga do ikony: bez zgodnego `StartupWMClass`
     * pulpit nie skojarzy otwartego okna z wpisem.
     */
    public function testEntryDeclaresTheSameWindowClassTheWindowAnnounces(): void
    {
        $this->installer()->install();

        $entry = (string) file_get_contents($this->home . '/.local/share/applications/light-manager.desktop');

        self::assertStringContainsString('StartupWMClass=' . GlfwWindowService::WINDOW_CLASS, $entry);
        self::assertStringContainsString('Icon=' . DesktopEntryInstaller::ENTRY_NAME, $entry);
        self::assertStringContainsString('Exec="/opt/lm/bin/light-manager" --window', $entry);
    }

    /** Wpis pulpitu jest plikiem wierszowym — napis z nową linią rozbiłby go na dwa klucze. */
    public function testMultilineValuesNeverBreakTheEntryIntoLines(): void
    {
        (new DesktopEntryInstaller(
            Theme::grafit(),
            $this->home,
            '/opt/lm/bin/light-manager',
            "Light\nManager",
            "opis\nw dwóch wierszach",
        ))->install();

        $entry = (string) file_get_contents($this->home . '/.local/share/applications/light-manager.desktop');

        self::assertStringContainsString('Name=Light Manager', $entry);
        self::assertStringContainsString('Comment=opis w dwóch wierszach', $entry);
        self::assertCount(10, array_filter(explode("\n", $entry)), 'wpis ma dziesięć wierszy, nie więcej');
    }

    private function installer(): DesktopEntryInstaller
    {
        return new DesktopEntryInstaller(
            Theme::grafit(),
            $this->home,
            '/opt/lm/bin/light-manager',
            'Light Manager',
            'Menadżer plików',
        );
    }

    private static function removeDirectory(string $directory): void
    {
        $entries = scandir($directory);

        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;

            is_dir($path) ? self::removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
