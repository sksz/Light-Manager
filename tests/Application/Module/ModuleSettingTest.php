<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Module;

use LightManager\Application\Module\ModuleSetting;
use LightManager\Application\Module\ModuleSettingKind;
use PHPUnit\Framework\TestCase;

/**
 * Deklaracja pozycji w zakładce ustawień modułu.
 *
 * Pozycja jest **daną**, więc wszystko, co robi, da się sprawdzić bez ekranu:
 * jak czyta wartość z pliku, jak ją przesuwa strzałką i co przyjmuje w polu
 * tekstowym. To ostatnie jest jedynym miejscem, w którym rdzeń sprawdza wartość
 * według deklaracji modułu (P13).
 */
final class ModuleSettingTest extends TestCase
{
    public function testToggleReadsBooleansAndFallsBackToItsDefault(): void
    {
        $setting = ModuleSetting::toggle('loud', 'module.x.setting.loud', true);

        self::assertFalse($setting->valueFrom(false));
        self::assertTrue($setting->valueFrom('nie-boolean'), 'wartość obcego typu wraca do domyślnej');
        self::assertTrue($setting->valueFrom(null), 'brak wpisu w pliku to też wartość domyślna');
    }

    public function testToggleIgnoresDirection(): void
    {
        $setting = ModuleSetting::toggle('loud', 'module.x.setting.loud', false);

        self::assertTrue($setting->shifted(false, -1));
        self::assertTrue($setting->shifted(false, 1));
    }

    public function testNumberAcceptsOnlyValuesFromItsList(): void
    {
        $setting = ModuleSetting::number('timeout', 'module.x.setting.timeout', [1, 2, 5], 2);

        self::assertSame(5, $setting->valueFrom(5));
        self::assertSame(2, $setting->valueFrom(7), 'liczba spoza listy wraca do domyślnej');
        self::assertSame(2, $setting->valueFrom('5'), 'napis nie jest liczbą');
    }

    public function testChoiceAndNumberWalkTheirListInBothDirections(): void
    {
        $setting = ModuleSetting::number('timeout', 'module.x.setting.timeout', [1, 2, 5], 1);

        self::assertSame(2, $setting->shifted(1, 1));
        self::assertSame(1, $setting->shifted(5, 1), 'lista zawija się na końcu');
        self::assertSame(5, $setting->shifted(1, -1));
        self::assertSame(1, $setting->shifted(9, 1), 'wartość spoza listy wraca na jej początek');
    }

    public function testTextPositionDoesNotShift(): void
    {
        $setting = ModuleSetting::text('arguments', 'module.x.setting.arguments');

        self::assertSame('-k', $setting->shifted('-k', 1));
        self::assertSame(ModuleSettingKind::Text, $setting->kind);
    }

    public function testTextRespectsPatternAndLength(): void
    {
        $setting = ModuleSetting::text('arguments', 'module.x.setting.arguments', '', '/^[a-z ]*$/u', 5);

        self::assertTrue($setting->accepts('abc'));
        self::assertTrue($setting->accepts(''), 'pusta wartość znaczy „nic tu nie chcę”');
        self::assertFalse($setting->accepts('ABC'), 'wzorzec');
        self::assertFalse($setting->accepts('abcdef'), 'długość maksymalna');
    }

    public function testStoredTextThatNoLongerFitsTheDeclarationFallsBack(): void
    {
        $setting = ModuleSetting::text('arguments', 'module.x.setting.arguments', 'domyślne', '/^[a-z]*$/u');

        self::assertSame('domyślne', $setting->valueFrom('WIELKIE'));
        self::assertSame('abc', $setting->valueFrom('abc'));
    }

    /** Bez wzorca i bez limitu pozycja tekstowa przyjmuje wszystko. */
    public function testTextWithoutConstraintsAcceptsAnything(): void
    {
        $setting = ModuleSetting::text('note', 'module.x.setting.note');

        self::assertTrue($setting->accepts('cokolwiek — nawet "to"'));
    }
}
