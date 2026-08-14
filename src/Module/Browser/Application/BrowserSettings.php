<?php

declare(strict_types=1);

namespace LightManager\Module\Browser\Application;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;

/**
 * Ustawienia modułu w jednym miejscu: deklaracja pozycji i odczyt wartości.
 *
 * Do kroku 20 widoczność wpisów ukrytych była kluczem **rdzenia**
 * (`SettingKey::ShowHiddenEntries`), bo rdzeń znał wtedy pojęcie katalogu. Po
 * przenosinach nie zna go już wcale, więc ustawienie zeszło razem z nawigacją
 * i leży w podprzestrzeni `modules.browser` (D40, P8).
 *
 * Jedna pozycja, ale sprawdza mechanizm z kroku 20 mocniej niż obie pozycje
 * modułu `FileInfo`: zmienia się nie tylko na ekranie ustawień, ale i **klawiszem**
 * (`.` w przeglądarce), w środku klatki, wraz z ponownym odczytem katalogu.
 */
final class BrowserSettings
{
    public const ID = 'browser';

    public const SHOW_HIDDEN = 'showHidden';

    public const DEFAULT_SHOW_HIDDEN = false;

    /** Podział ekranu na dwa panele (krok 24) — ustawienie **modułu**, nie rdzenia. */
    public const SPLIT = 'split';

    public const DEFAULT_SPLIT = false;

    /**
     * Czy granica podziału biegnie pionowo, czyli panele stoją obok siebie.
     *
     * Przełącznik, a nie wybór z listy, i to nie z lenistwa: wartości wyboru
     * pokazuje ekran ustawień **surowo**, bez katalogu napisów, więc „vertical”
     * zostałoby w polskim interfejsie po angielsku. Przełącznik pokazuje „tak”
     * i „nie” przetłumaczone, a oś ma dokładnie dwie wartości.
     */
    public const SPLIT_VERTICAL = 'splitVertical';

    public const DEFAULT_SPLIT_VERTICAL = true;

    /**
     * Kolumny szczegółów: data zmiany i prawa dostępu (krok 27).
     *
     * Jeden przełącznik na obie, a nie po jednym na kolumnę, i to jest
     * rozstrzygnięcie: kolejność ustępowania w wąskim oknie i tak musi być
     * zaprogramowana, więc cztery przełączniki dawałyby użytkownikowi władzę
     * nad tym, co i tak zniknie samo. Domyślnie **włączone** — kolumny są
     * głównym powodem, dla którego krok powstał, a w wąskim panelu ustąpią bez
     * pytania.
     */
    public const DETAILS = 'details';

    public const DEFAULT_DETAILS = true;

    /** Wiersz z nazwami kolumn nad listą — kosztuje wiersz, więc domyślnie go nie ma. */
    public const COLUMN_HEADER = 'columnHeader';

    public const DEFAULT_COLUMN_HEADER = false;

    /**
     * Ile poziomów pokazuje drzewo (krok 31) — wraz z wartością „bez limitu”.
     *
     * Pozycja wyboru, a nie liczbowa, i to z jednego powodu: **„bez limitu” nie
     * jest liczbą**. Wariant liczbowy musiałby udawać ją zerem albo wielkim
     * przybliżeniem, a ekran ustawień pokazuje wartości wyboru **surowo**, bez
     * katalogu napisów — użytkownik zobaczyłby więc „0” i musiał się domyślić.
     * Znak nieskończoności czyta się tak samo w każdym języku, więc surowość
     * ekranu przestaje tu być wadą.
     *
     * Domyślne osiem, a nie „bez limitu”, bo limit ma być widoczny: drzewo
     * rozwijane bez granicy spycha nazwę wcięciem poza panel, a użytkownik, który
     * tego chce, ma pozycję pod ręką. Poziomem pierwszym są wpisy katalogu
     * w korzeniu, więc dwójka znaczy „katalog i jego dzieci”.
     */
    public const TREE_DEPTH = 'treeDepth';

    /** Wartość oznaczająca brak limitu — jedyna spoza zakresu liczb. */
    public const TREE_DEPTH_UNLIMITED = '∞';

    /** @var list<string> */
    public const TREE_DEPTH_CHOICES = ['2', '3', '4', '5', '6', '8', '12', self::TREE_DEPTH_UNLIMITED];

    public const DEFAULT_TREE_DEPTH = '8';

    /** @return list<ModuleSetting> */
    public static function declarations(): array
    {
        return [
            ModuleSetting::toggle(
                self::SHOW_HIDDEN,
                'module.' . self::ID . '.setting.' . self::SHOW_HIDDEN,
                self::DEFAULT_SHOW_HIDDEN,
            ),
            ModuleSetting::toggle(
                self::SPLIT,
                'module.' . self::ID . '.setting.' . self::SPLIT,
                self::DEFAULT_SPLIT,
            ),
            ModuleSetting::toggle(
                self::SPLIT_VERTICAL,
                'module.' . self::ID . '.setting.' . self::SPLIT_VERTICAL,
                self::DEFAULT_SPLIT_VERTICAL,
            ),
            ModuleSetting::toggle(
                self::DETAILS,
                'module.' . self::ID . '.setting.' . self::DETAILS,
                self::DEFAULT_DETAILS,
            ),
            ModuleSetting::toggle(
                self::COLUMN_HEADER,
                'module.' . self::ID . '.setting.' . self::COLUMN_HEADER,
                self::DEFAULT_COLUMN_HEADER,
            ),
            ModuleSetting::choice(
                self::TREE_DEPTH,
                'module.' . self::ID . '.setting.' . self::TREE_DEPTH,
                self::TREE_DEPTH_CHOICES,
                self::DEFAULT_TREE_DEPTH,
            ),
        ];
    }

    public static function showHidden(Settings $settings): bool
    {
        return self::flag($settings, self::declaration(), self::DEFAULT_SHOW_HIDDEN);
    }

    /** Czy użytkownik chce dwa panele. O tym, czy się mieszczą, rozstrzyga `Split`. */
    public static function split(Settings $settings): bool
    {
        return self::flag($settings, self::declarations()[1], self::DEFAULT_SPLIT);
    }

    public static function splitVertical(Settings $settings): bool
    {
        return self::flag($settings, self::declarations()[2], self::DEFAULT_SPLIT_VERTICAL);
    }

    /** Czy lista pokazuje datę zmiany i prawa dostępu obok nazwy i rozmiaru. */
    public static function details(Settings $settings): bool
    {
        return self::flag($settings, self::declarations()[3], self::DEFAULT_DETAILS);
    }

    public static function columnHeader(Settings $settings): bool
    {
        return self::flag($settings, self::declarations()[4], self::DEFAULT_COLUMN_HEADER);
    }

    /**
     * Ile poziomów wolno pokazać drzewu; `null` — bez limitu.
     *
     * Odpowiedź jest `null`-owalna, a nie „bardzo duża”, bo wołający i tak musi
     * odróżnić te dwa przypadki: przy limicie osiągniętym mówi użytkownikowi,
     * dlaczego gałąź się nie rozwija, a bez limitu nie ma o czym mówić.
     */
    public static function treeDepth(Settings $settings): ?int
    {
        $setting = self::declarations()[5];
        $value = $setting->valueFrom($settings->moduleValue(self::ID, $setting->key));

        if (!is_string($value) || $value === self::TREE_DEPTH_UNLIMITED) {
            return null;
        }

        return (int) $value;
    }

    private static function flag(Settings $settings, ModuleSetting $setting, bool $default): bool
    {
        $value = $setting->valueFrom($settings->moduleValue(self::ID, $setting->key));

        return is_bool($value) ? $value : $default;
    }

    /** Deklaracja pozycji „wpisy ukryte” — potrzebna ekranowi przy zmianie klawiszem. */
    public static function declaration(): ModuleSetting
    {
        return self::declarations()[0];
    }
}
