<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Wynik wczytania konfiguracji: wartości plus ewentualny powód, dla którego nie
 * wszystkie pochodzą z pliku.
 *
 * Problem wraca jako tekst, a nie jako wyjątek, bo uszkodzony plik nie ma prawa
 * przerwać startu — ma się pokazać w pasku stanu i tyle. Wyjątki infrastruktury
 * zostają po stronie infrastruktury; `Application` nie ma prawa ich znać.
 */
final class LoadedSettings
{
    public function __construct(
        public readonly Settings $settings,
        public readonly ?string $problem = null,
    ) {
    }
}
