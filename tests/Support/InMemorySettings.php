<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Dto\LoadedSettings;
use LightManager\Application\Dto\Settings;
use LightManager\Application\Port\SettingsPort;

/**
 * Konfiguracja bez pliku — pozwala sprawdzić zmianę ustawień bez dotykania
 * katalogu domowego użytkownika.
 *
 * `failWith()` udaje dysk, na który nie da się zapisać: to jedyna ścieżka, po
 * której `save()` oddaje powód zamiast `null`.
 */
final class InMemorySettings implements SettingsPort
{
    /** @var list<Settings> */
    public array $saved = [];

    private ?string $failure = null;

    public function __construct(
        private Settings $settings = new Settings(),
        private readonly ?string $problem = null,
    ) {
    }

    public function failWith(string $problem): self
    {
        $this->failure = $problem;

        return $this;
    }

    public function load(array $themeNames): LoadedSettings
    {
        return new LoadedSettings($this->settings, $this->problem);
    }

    public function current(): Settings
    {
        return $this->settings;
    }

    public function save(Settings $settings): ?string
    {
        if ($this->failure !== null) {
            return $this->failure;
        }

        $this->settings = $settings;
        $this->saved[] = $settings;

        return null;
    }

    public function location(): string
    {
        return '/dom/testowy/.light-manager/settings.json';
    }
}
