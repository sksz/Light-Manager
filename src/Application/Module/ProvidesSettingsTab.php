<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Moduł, który wnosi własną zakładkę do okna konfiguracji.
 *
 * Zdolność leży w `Application`, bo wymienia wyłącznie dane — zakładka opisana
 * `ModuleSetting`ami nie zna ani jednego typu z `Presentation` (P2).
 */
interface ProvidesSettingsTab
{
    public function settingsTab(): ModuleSettingsTab;
}
