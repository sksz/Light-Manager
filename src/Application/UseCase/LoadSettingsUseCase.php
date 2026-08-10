<?php

declare(strict_types=1);

namespace LightManager\Application\UseCase;

use LightManager\Application\Dto\LoadedSettings;
use LightManager\Application\Port\SettingsPort;
use LightManager\Application\Port\ThemePort;

/**
 * Wczytuje konfigurację przy starcie aplikacji.
 *
 * Cała robota polega na zestawieniu dwóch portów: katalog motywów wie, jakie
 * nazwy palet są dopuszczalne, a nośnik konfiguracji sprawdza według tej listy
 * wartość klucza `theme`. Bez tego kroku usługa konfiguracji musiałaby znać
 * warstwę renderowania — a nie ma powodu, żeby ją znała.
 */
final class LoadSettingsUseCase
{
    public function __construct(
        private readonly SettingsPort $settings,
        private readonly ThemePort $themes,
    ) {
    }

    public function execute(): LoadedSettings
    {
        return $this->settings->load($this->themes->names());
    }
}
