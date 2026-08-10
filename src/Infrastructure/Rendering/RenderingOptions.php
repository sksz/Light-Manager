<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Rendering;

use LightManager\Infrastructure\Config\SettingsService;

/**
 * Wszystko, co potok Sixela bierze z konfiguracji, zebrane w jedną wartość
 * obowiązującą przez jedną klatkę.
 *
 * Do kroku 14 te cztery rzeczy były stałymi w enkoderze — motyw wstrzykiwany
 * raz przy budowie usługi, wygładzanie i wielkość palety wpisane w kod (D18,
 * D27). Ustawienia zmieniają się w trakcie działania aplikacji, więc enkoder
 * nie może ich pamiętać dłużej niż klatkę: renderer składa ten obiekt przed
 * każdym rysowaniem i podaje go dalej.
 */
final class RenderingOptions
{
    public function __construct(
        public readonly Theme $theme,
        public readonly bool $textAntialias,
        public readonly bool $strokeAntialias,
        public readonly int $paletteColors,
        /**
         * Nazwa fontu albo `null` — „wybierz najlepszy dostępny”.
         *
         * Aplikacja zawsze podaje `null` i zdaje się na listę preferencji
         * `ImagickCapabilityService`; pole istnieje dla narzędzia pomiarowego
         * z kroku 16, w którym font jest osią konfiguracji przestawianą z linii
         * poleceń. Wartość domyślna sprawia, że dla reszty kodu nic się nie
         * zmienia.
         */
        public readonly ?string $font = null,
    ) {
    }

    /** Stan bieżący: motyw z katalogu, reszta wprost z konfiguracji. */
    public static function current(): self
    {
        $settings = SettingsService::getInstance()->current();

        return new self(
            ThemeService::getInstance()->named($settings->theme),
            $settings->textAntialias,
            $settings->strokeAntialias,
            $settings->paletteColors,
        );
    }
}
