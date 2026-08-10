<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Zakładka modułu w oknie konfiguracji — etykieta plus pozycje.
 *
 * Zakładka jest opisem, nie ekranem: nie rysuje się sama i nie obsługuje
 * klawiszy. Robi to rdzeń, dokładnie tak samo jak z własnymi zakładkami, więc
 * moduł nie ma jak narysować pozycji inaczej niż reszta aplikacji.
 */
final class ModuleSettingsTab
{
    /** @param list<ModuleSetting> $settings */
    public function __construct(
        /** Klucz katalogu z nazwą zakładki — zwykle `module.<id>.name`. */
        public readonly string $labelKey,
        public readonly array $settings,
    ) {
    }
}
