<?php

declare(strict_types=1);

namespace LightManager\Tests\Application\UseCase;

use LightManager\Application\Dto\SettingKey;
use LightManager\Application\Dto\Settings;
use LightManager\Application\UseCase\ChangeSettingUseCase;
use LightManager\Domain\ValueObject\MessageTone;
use LightManager\Tests\Support\FixedThemes;
use LightManager\Tests\Support\InMemorySettings;
use LightManager\Tests\Support\StubTranslator;
use PHPUnit\Framework\TestCase;

final class ChangeSettingUseCaseTest extends TestCase
{
    private InMemorySettings $settings;

    private ChangeSettingUseCase $useCase;

    protected function setUp(): void
    {
        $this->settings = new InMemorySettings();
        $this->useCase = new ChangeSettingUseCase($this->settings, new FixedThemes(), new StubTranslator());
    }

    public function testChangeIsSavedImmediately(): void
    {
        [$changed, $message] = $this->useCase->execute(new Settings(), SettingKey::Theme, 1);

        self::assertSame('nordyk', $changed->theme);
        self::assertNull($message);
        self::assertCount(1, $this->settings->saved);
        self::assertSame('nordyk', $this->settings->saved[0]->theme);
    }

    /** Katalog z jednym motywem nie ma na co się przełączyć — nie ma też czego zapisywać. */
    public function testChangeThatChangesNothingDoesNotTouchTheFile(): void
    {
        $useCase = new ChangeSettingUseCase($this->settings, new FixedThemes(['grafit']), new StubTranslator());

        [$changed, $message] = $useCase->execute(new Settings(), SettingKey::Theme, 1);

        self::assertSame('grafit', $changed->theme);
        self::assertNull($message);
        self::assertSame([], $this->settings->saved);
    }

    public function testPaletteBelowThresholdWarnsButKeepsTheValue(): void
    {
        [$changed, $message] = $this->useCase->execute(new Settings(), SettingKey::PaletteColors, -1);

        self::assertSame(32, $changed->paletteColors);
        self::assertSame(MessageTone::Warning, $message?->tone);
    }

    public function testPaletteAtOrAboveThresholdIsSilent(): void
    {
        [$changed, $message] = $this->useCase->execute(new Settings(), SettingKey::PaletteColors, 1);

        self::assertSame(128, $changed->paletteColors);
        self::assertNull($message);
    }

    /** Inne ustawienie nie dziedziczy ostrzeżenia po palecie, choćby ta stała nisko. */
    public function testWarningBelongsToThePaletteAlone(): void
    {
        $low = (new Settings())->withPaletteColors(16);

        [, $message] = $this->useCase->execute($low, SettingKey::TextAntialias, 1);

        self::assertNull($message);
    }

    /**
     * Nieudany zapis nie cofa zmiany: ustawienie działa do końca uruchomienia,
     * a użytkownik dowiaduje się, że nie przetrwa następnego.
     */
    public function testFailedWriteReportsErrorAndKeepsTheChange(): void
    {
        $this->settings->failWith('Nie można zapisać pliku konfiguracji.');

        [$changed, $message] = $this->useCase->execute(new Settings(), SettingKey::Theme, 1);

        self::assertSame('nordyk', $changed->theme);
        self::assertSame(MessageTone::Error, $message?->tone);
    }
}
