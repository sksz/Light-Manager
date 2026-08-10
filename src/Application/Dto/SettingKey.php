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
 */
enum SettingKey: string
{
    case Language = 'language';
    case Theme = 'theme';
    case ShowHiddenEntries = 'showHiddenEntries';
    case TextAntialias = 'textAntialias';
    case StrokeAntialias = 'strokeAntialias';
    case PaletteColors = 'paletteColors';

    public function labelKey(): string
    {
        return 'settings.key.' . $this->value;
    }
}
