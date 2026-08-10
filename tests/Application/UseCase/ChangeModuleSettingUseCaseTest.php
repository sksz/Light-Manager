<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\UseCase;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;
use LightManager\Application\UseCase\ChangeModuleSettingUseCase;
use LightManager\Application\UseCase\ChangeSettingUseCase;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

/**
 * Zmiana pozycji w zakładce ustawień modułu.
 *
 * Osobny przypadek użycia obok {@see ChangeSettingUseCase}, bo różni się
 * wszystkim poza zapisem: pozycję opisuje deklaracja, wartość leży
 * w podprzestrzeni modułu, a przy tekście dochodzi walidacja, której ustawienia
 * rdzenia nie mają.
 */
final class ChangeModuleSettingUseCaseTest extends TestCase
{
    private InMemorySettings $store;

    private ChangeModuleSettingUseCase $useCase;

    protected function setUp(): void
    {
        $this->store = new InMemorySettings();
        $this->useCase = new ChangeModuleSettingUseCase($this->store, new StubTranslator());
    }

    public function testShiftWritesIntoTheModuleSubspaceAndSaves(): void
    {
        $setting = ModuleSetting::number('timeout', 'module.x.setting.timeout', [1, 2, 5], 2);

        [$settings, $message] = $this->useCase->shift(new Settings(), 'x', $setting, 1);

        self::assertSame(5, $settings->moduleValue('x', 'timeout'));
        self::assertNull($message);
        self::assertCount(1, $this->store->saved, 'ustawienie ma przeżyć zabicie procesu');
    }

    public function testShiftStartsFromWhatIsAlreadyStored(): void
    {
        $setting = ModuleSetting::number('timeout', 'module.x.setting.timeout', [1, 2, 5], 1);
        $current = (new Settings())->withModuleValue('x', 'timeout', 5);

        [$settings] = $this->useCase->shift($current, 'x', $setting, 1);

        self::assertSame(1, $settings->moduleValue('x', 'timeout'), 'lista zawija się na końcu');
    }

    public function testSettingsOfOtherModulesAreLeftUntouched(): void
    {
        $current = (new Settings())->withModuleValue('inny', 'klucz', 'wartość');
        $setting = ModuleSetting::toggle('loud', 'module.x.setting.loud', false);

        [$settings] = $this->useCase->shift($current, 'x', $setting, 1);

        self::assertSame('wartość', $settings->moduleValue('inny', 'klucz'));
    }

    public function testTextValueAgainstThePatternIsRejectedWithoutOverwritingThePreviousOne(): void
    {
        $setting = ModuleSetting::text('arguments', 'module.x.setting.arguments', '', '/^[a-z]*$/u');
        $current = (new Settings())->withModuleValue('x', 'arguments', 'abc');

        [$settings, $message] = $this->useCase->set($current, 'x', $setting, 'ŹLE!');

        self::assertSame('abc', $settings->moduleValue('x', 'arguments'), 'poprzednia wartość zostaje');
        self::assertSame(MessageTone::Error, $message?->tone);
        self::assertSame([], $this->store->saved, 'odrzucona wartość nie dotyka dysku');
    }

    public function testAcceptedTextValueIsStored(): void
    {
        $setting = ModuleSetting::text('arguments', 'module.x.setting.arguments', '', '/^[a-z]*$/u');

        [$settings, $message] = $this->useCase->set(new Settings(), 'x', $setting, 'abc');

        self::assertSame('abc', $settings->moduleValue('x', 'arguments'));
        self::assertNull($message);
    }

    /** Zmiana przełącznika modułu zadziała dopiero po restarcie — i mówi o tym wprost. */
    public function testEnablingAModuleSaysWhenItTakesEffect(): void
    {
        [$settings, $message] = $this->useCase->enable(new Settings(), 'x', false);

        self::assertFalse($settings->moduleValue('x', 'enabled'));
        self::assertNotNull($message);
        self::assertSame(MessageTone::Info, $message->tone);
        self::assertSame('module.restart', $message->text);
    }

    public function testFailedSaveKeepsTheChangeAndSaysItWillNotSurvive(): void
    {
        $this->store->failWith('dysk tylko do odczytu');
        $setting = ModuleSetting::toggle('loud', 'module.x.setting.loud', false);

        [$settings, $message] = $this->useCase->shift(new Settings(), 'x', $setting, 1);

        self::assertTrue($settings->moduleValue('x', 'loud'));
        self::assertSame(MessageTone::Error, $message?->tone);
    }

    public function testValueThatDoesNotChangeAnythingDoesNotTouchTheDisk(): void
    {
        $current = (new Settings())->withModuleValue('x', 'arguments', 'abc');
        $setting = ModuleSetting::text('arguments', 'module.x.setting.arguments');

        [$settings, $message] = $this->useCase->set($current, 'x', $setting, 'abc');

        self::assertTrue($settings->equals($current));
        self::assertNull($message);
        self::assertSame([], $this->store->saved);
    }
}
