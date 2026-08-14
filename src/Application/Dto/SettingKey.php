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
 *
 * `WindowColumns` i `WindowRows` (krok 34, D53) opisują rozmiar okna trybu
 * okienkowego w komórkach siatki znakowej. W trybach terminalowych nie robią
 * nic — rozmiar dyktuje tam terminal — ale zostają widoczne na ekranie
 * ustawień, bo plik konfiguracji jest jeden dla wszystkich trybów.
 *
 * Od kroku 37 nie są już wyłącznie **rozmiarem startowym**: okno zapisuje pod
 * nie rozmiar nadany przeciągnięciem rogu albo maksymalizacją, więc te dwa
 * klucze są zarazem jedynym miejscem, w którym pamięta się ustawione okno.
 * Stąd ich wartości sprawdza zakres, a nie lista przystanków strzałek.
 */
enum SettingKey: string
{
    case Language = 'language';
    case Theme = 'theme';
    case StartupModule = 'startupModule';
    case TextAntialias = 'textAntialias';
    case StrokeAntialias = 'strokeAntialias';
    case PaletteColors = 'paletteColors';
    case WindowColumns = 'windowColumns';
    case WindowRows = 'windowRows';

    public function labelKey(): string
    {
        return 'settings.key.' . $this->value;
    }
}
