<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Dto;

use LightManager\Application\Dto\Language;
use LightManager\Application\Dto\SettingKey;
use LightManager\Application\Dto\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    /** @var list<string> */
    private const THEMES = ['grafit', 'nordyk', 'papier', 'indygo'];

    public function testDefaultsMatchTheMeasurementsOfStepThirteen(): void
    {
        $settings = new Settings();

        self::assertSame(Language::Auto->value, $settings->language);
        self::assertSame('grafit', $settings->theme);
        self::assertSame('browser', $settings->startupModule);
        self::assertFalse($settings->textAntialias);
        self::assertTrue($settings->strokeAntialias);
        self::assertSame(64, $settings->paletteColors);
    }

    public function testThemeCyclesForwardAndBackwards(): void
    {
        $settings = new Settings();

        self::assertSame('nordyk', $settings->shifted(SettingKey::Theme, 1, self::THEMES)->theme);
        self::assertSame('indygo', $settings->shifted(SettingKey::Theme, -1, self::THEMES)->theme);
    }

    public function testThemeWrapsAroundBothEnds(): void
    {
        $last = (new Settings())->withTheme('indygo');

        self::assertSame('grafit', $last->shifted(SettingKey::Theme, 1, self::THEMES)->theme);
    }

    /** Ręcznie wpisana nazwa spoza katalogu nie ma prawa zablokować przełącznika. */
    public function testUnknownThemeFallsBackToTheFirstOne(): void
    {
        $broken = (new Settings())->withTheme('nieistniejacy');

        self::assertSame('grafit', $broken->shifted(SettingKey::Theme, 1, self::THEMES)->theme);
    }

    /**
     * Moduł domyślny jest pierwszym kluczem rdzenia, którego dopuszczalne wartości
     * nie są znane w czasie pisania kodu — przychodzą z rejestru modułów.
     */
    public function testStartupModuleCyclesThroughTheListItIsGiven(): void
    {
        $settings = new Settings();
        $modules = ['browser', 'file-info'];

        self::assertSame(
            'file-info',
            $settings->shifted(SettingKey::StartupModule, 1, self::THEMES, $modules)->startupModule,
        );
        self::assertSame(
            'browser',
            $settings->shifted(SettingKey::StartupModule, 1, self::THEMES, $modules)
                ->shifted(SettingKey::StartupModule, 1, self::THEMES, $modules)
                ->startupModule,
        );
    }

    /** Bez listy z rejestru przełącznik nie ma dokąd pójść i zostawia wartość. */
    public function testStartupModuleWithoutChoicesStaysWhereItIs(): void
    {
        $settings = new Settings();

        self::assertSame(
            'browser',
            $settings->shifted(SettingKey::StartupModule, 1, self::THEMES)->startupModule,
        );
    }

    /** @return array<string, array{SettingKey, int}> */
    public static function switches(): array
    {
        return [
            'wygładzanie tekstu' => [SettingKey::TextAntialias, 1],
            'wygładzanie obrysów, w lewo' => [SettingKey::StrokeAntialias, -1],
        ];
    }

    /** Przełącznik dwustanowy ma jednego sąsiada, więc kierunek go nie dotyczy. */
    #[DataProvider('switches')]
    public function testTwoStateSwitchesIgnoreDirection(SettingKey $key, int $direction): void
    {
        $settings = new Settings();
        $once = $settings->shifted($key, $direction, self::THEMES);

        self::assertFalse($settings->equals($once));
        self::assertTrue($settings->equals($once->shifted($key, $direction, self::THEMES)));
    }

    public function testPaletteWalksThroughItsChoices(): void
    {
        $settings = new Settings();

        self::assertSame(128, $settings->shifted(SettingKey::PaletteColors, 1, self::THEMES)->paletteColors);
        self::assertSame(32, $settings->shifted(SettingKey::PaletteColors, -1, self::THEMES)->paletteColors);
    }

    /** Język chodzi po tej samej liście, co reszta przełączników wielostanowych. */
    public function testLanguageCyclesThroughAutomaticAndTheCataloguedLanguages(): void
    {
        $settings = new Settings();

        self::assertSame('pl', $settings->shifted(SettingKey::Language, 1, self::THEMES)->language);
        self::assertSame('en', $settings->shifted(SettingKey::Language, -1, self::THEMES)->language);
        self::assertSame(
            Language::Auto->value,
            $settings->withLanguage('en')->shifted(SettingKey::Language, 1, self::THEMES)->language,
        );
    }

    /** Klucz każdego ustawienia wskazuje napis w katalogu, a nie sam napis. */
    public function testEverySettingNamesItsLabelByKey(): void
    {
        foreach (SettingKey::cases() as $key) {
            self::assertSame('settings.key.' . $key->value, $key->labelKey());
        }
    }

    public function testEqualsComparesEveryValue(): void
    {
        $settings = new Settings();

        self::assertTrue($settings->equals(new Settings()));
        self::assertFalse($settings->equals($settings->withPaletteColors(128)));
    }
}
