<?php

declare(strict_types=1);

namespace LightManager\Tests\Support;

use LightManager\Application\Module\ModuleInterface;
use LightManager\Application\Module\ModuleSettingsTab;
use LightManager\Application\Module\ModuleShortcut;
use LightManager\Application\Module\ProvidesSettingsTab;

/**
 * Moduł na potrzeby testów rejestru — sama deklaracja, bez ekranu i komend.
 *
 * Istnieje po to, żeby sprawdzić rzeczy, których moduł wbudowany sprawdzić nie
 * może: kolizję skrótów, literę zabronioną, powtórzony identyfikator i to, że
 * **moduł bez ani jednej zdolności jest legalny** (P17). Testowanie tego na
 * `FileInfo` wymagałoby psucia go na chwilę.
 *
 * Zakładka ustawień jest opcjonalna, bo jedno z tych sprawdzeń dotyczy modułu,
 * który wnosi wyłącznie ją.
 */
final class FakeModule implements ModuleInterface, ProvidesSettingsTab
{
    public function __construct(
        private readonly string $id,
        private readonly ?ModuleShortcut $shortcut = null,
        private readonly ?ModuleSettingsTab $settingsTab = null,
        private readonly ?string $translations = null,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function nameKey(): string
    {
        return 'module.' . $this->id . '.name';
    }

    public function descriptionKey(): string
    {
        return 'module.' . $this->id . '.description';
    }

    public function shortcut(): ?ModuleShortcut
    {
        return $this->shortcut;
    }

    public function translations(): ?string
    {
        return $this->translations;
    }

    public function settingsTab(): ModuleSettingsTab
    {
        return $this->settingsTab ?? new ModuleSettingsTab($this->nameKey(), []);
    }
}
