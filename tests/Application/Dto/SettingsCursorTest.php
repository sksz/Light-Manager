<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\Dto;

use LightManager\Application\Dto\SettingKey;
use LightManager\Application\Dto\SettingsCursor;
use LightManager\Application\Dto\SettingsTab;
use LightManager\Application\Module\ModuleSetting;
use LightManager\Application\Module\ModuleSettingsTab;
use PHPUnit\Framework\TestCase;

/**
 * Kursor ekranu ustawień po otwarciu zakładek na moduły (krok 20).
 *
 * Zakładki nie są już przypadkami enuma, więc kursor dostaje ich listę
 * z zewnątrz. Test buduje ją tak, jak robi to `Bootstrap`: **trzy** rdzeniowe
 * (od kroku 49 doszły „Zasoby”), spis modułów, na końcu zakładka modułu.
 */
final class SettingsCursorTest extends TestCase
{
    /** @return list<SettingsTab> */
    private static function tabs(): array
    {
        $tabs = SettingsTab::coreTabs();
        $tabs[] = SettingsTab::modules(1);
        $tabs[] = SettingsTab::ofModule('sample', new ModuleSettingsTab('module.sample.name', [
            ModuleSetting::toggle('loud', 'module.sample.setting.loud', false),
        ]));

        return $tabs;
    }

    private static function cursor(int $tab = 0, ?int $item = null): SettingsCursor
    {
        return new SettingsCursor(self::tabs(), $tab, $item);
    }

    public function testStartsOnTheTabBarOfTheFirstTab(): void
    {
        $cursor = self::cursor();

        self::assertTrue($cursor->isOnTabBar());
        self::assertSame('settings.tab.appearance', $cursor->activeTab()?->labelKey);
        self::assertNull($cursor->key());
    }

    public function testMovingDownFromTheTabBarEntersTheFirstPosition(): void
    {
        $cursor = self::cursor()->movedBy(1);

        self::assertSame(0, $cursor->item);
        self::assertSame(SettingKey::Language, $cursor->key());
    }

    public function testMovingUpFromTheFirstPositionReturnsToTheTabBar(): void
    {
        self::assertTrue(self::cursor()->movedBy(1)->movedBy(-1)->isOnTabBar());
    }

    /**
     * Pionowy ruch nie zawija się — na krańcu kursor po prostu zostaje.
     *
     * Ostatnim miejscem zakładki rdzenia nie jest ostatnie ustawienie, tylko
     * **wiersz czynności** pod nim: od kroku 18 stoi tam przycisk przywracania
     * ustawień domyślnych (P12).
     */
    public function testCursorStopsOnTheActionRowUnderTheLastPosition(): void
    {
        $last = self::cursor(1, 2)->movedBy(1);

        self::assertSame(3, $last->item);
        self::assertTrue($last->isOnAction());
        self::assertNull($last->key(), 'czynność nie jest ustawieniem');
        self::assertSame(3, $last->movedBy(1)->item, 'dalej już nie ma dokąd zejść');
    }

    /**
     * Zakładka modułu **nie ma** wiersza czynności: przycisk przywraca ustawienia
     * rdzenia, więc postawiony pod nią obiecywałby coś, czego nie robi.
     */
    public function testModuleTabHasNoActionRow(): void
    {
        $cursor = self::cursor(4, 0);

        self::assertFalse($cursor->isOnAction());
        self::assertSame(0, $cursor->movedBy(1)->item, 'jedyna pozycja jest zarazem ostatnim miejscem');
        self::assertNotNull($cursor->setting());
        self::assertNull($cursor->key(), 'pozycja modułu nie jest ustawieniem rdzenia');
    }

    public function testTabSwitchingCyclesThroughEveryTabAndKeepsTheCursorOnTheBar(): void
    {
        $cursor = self::cursor()->movedBy(1)->switchedTab(1);

        self::assertSame(1, $cursor->tab);
        self::assertTrue($cursor->isOnTabBar());
        self::assertSame(3, $cursor->switchedTab(1)->switchedTab(1)->tab, 'spis modułów stoi po zakładkach rdzenia');
        self::assertSame(4, $cursor->switchedTab(1)->switchedTab(1)->switchedTab(1)->tab);
        self::assertSame(
            0,
            $cursor->switchedTab(1)->switchedTab(1)->switchedTab(1)->switchedTab(1)->tab,
            'lista się zawija',
        );
        self::assertSame(0, $cursor->switchedTab(-1)->tab);
    }

    /** Wiersz kursora: pasek zakładek stoi w zerze, pozycje zaczynają się od drugiego. */
    public function testRowSkipsTheBlankLineUnderTheTabBar(): void
    {
        self::assertSame(0, self::cursor()->row());
        self::assertSame(2, self::cursor(0, 0)->row());
        self::assertSame(4, self::cursor(1, 2)->row());
    }

    public function testEveryCorePositionResolvesToASetting(): void
    {
        foreach ([0, 1, 2] as $tab) {
            $count = self::tabs()[$tab]->itemCount();

            for ($item = 0; $item < $count; ++$item) {
                self::assertNotNull(self::cursor($tab, $item)->key());
            }
        }
    }

    /** Pusta lista zakładek jest legalna — kursor stoi wtedy na pasku i nie schodzi. */
    public function testEmptyTabListLeavesTheCursorOnTheBar(): void
    {
        $cursor = new SettingsCursor([]);

        self::assertTrue($cursor->movedBy(1)->isOnTabBar());
        self::assertNull($cursor->activeTab());
        self::assertSame(0, $cursor->switchedTab(1)->tab);
    }
}
