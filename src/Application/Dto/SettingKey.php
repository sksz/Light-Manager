<?php

declare(strict_types=1);

namespace LightManager\Application\Dto;

/**
 * Pojedyncze ustawienie aplikacji — tożsamość pozycji na ekranie konfiguracji i
 * zarazem klucz w pliku JSON.
 *
 * Wartość enuma jest nazwą klucza w pliku, więc odczyt i zapis nie potrzebują
 * osobnej tablicy tłumaczącej. Nazwy są w `camelCase`, jak reszta pliku.
 *
 * Etykieta nie jest tu napisem, tylko kluczem katalogu: pozycja ma się nazywać
 * w języku interfejsu, a `Application` napisów nie przechowuje (krok 15).
 *
 * `StartupModule` jest pierwszym kluczem rdzenia, którego **dopuszczalne wartości
 * nie są znane w czasie pisania kodu** (krok 21): pochodzą z rejestru modułów
 * przyjętych w tym uruchomieniu. To jego jedyna nowość wobec `Theme` i `Language`
 * — i powód, dla którego lista wartości wędruje do `shifted()` z zewnątrz.
 *
 * `ShowHiddenEntries` **zniknął**: po przenosinach nawigacji do modułu widoczność
 * wpisów ukrytych jest ustawieniem przeglądarki (`modules.browser.showHidden`),
 * a nie aplikacji.
 */
enum SettingKey: string
{
    case Language = 'language';
    case Theme = 'theme';
    case StartupModule = 'startupModule';
    case TextAntialias = 'textAntialias';
    case StrokeAntialias = 'strokeAntialias';
    case PaletteColors = 'paletteColors';

    public function labelKey(): string
    {
        return 'settings.key.' . $this->value;
    }
}
