<?php

declare(strict_types=1);

namespace LightManager\Module\Audio\Application;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;

/**
 * Ustawienia modułu dźwięku: co grać, jak głośno i czy w kółko.
 *
 * Deklaracja i odczyt stoją obok siebie, wzorem `BrowserSettings`
 * i `FileInfoSettings` — są dwiema stronami tej samej umowy i rozdzielone
 * rozjechałyby się przy pierwszej zmianie listy wartości.
 *
 * **Autostartu tu nie ma i to jest rozstrzygnięcie, nie przeoczenie** (krok 36,
 * D70): moduł nie dostaje od rdzenia momentu startu, bo kontrakt modułu nie zna
 * cyklu życia, a dokładanie go dla jednego użytkownika byłoby rozszerzeniem
 * rdzenia dla wygody modułu. Muzyka rusza komendą `audio.music` — pozycja
 * „autostart”, której nie miałby co włączyć, byłaby obietnicą bez pokrycia.
 *
 * Głośność jest **liczbą z listy przystanków**, a nie dowolną wartością z zakresu,
 * i to wynika wprost z kontraktu ustawień modułu: `ModuleSetting::valueFrom()`
 * sprowadza wartość spoza listy do domyślnej, więc zapisane 63 przepadłoby przy
 * pierwszym odczycie. Dziesiątki wystarczają uchu, a strzałka na zakładce
 * przechodzi całą skalę w dziesięciu krokach.
 */
final class AudioSettings
{
    public const ID = 'audio';

    /** Ścieżka utworu — bezwzględna albo względna wobec korzenia projektu. */
    public const TRACK = 'track';

    public const VOLUME = 'volume';

    public const LOOP = 'loop';

    /**
     * Utwór domyślny leży w repozytorium, w `assets/audio/`.
     *
     * Ścieżka jest **względna wobec korzenia projektu**, a nie bezwzględna:
     * bezwzględna zapisałaby się do konfiguracji użytkownika przy pierwszej
     * zmianie i przestałaby działać po przeniesieniu katalogu z aplikacją.
     */
    public const DEFAULT_TRACK = 'assets/audio/Deep Purple - Smoke On The Water.mp3';

    /** Przystanki strzałki na zakładce — i zarazem jedyne wartości, które przyjmuje `audio.volume`. */
    public const VOLUME_CHOICES = [0, 10, 20, 30, 40, 50, 60, 70, 80, 90, 100];

    public const DEFAULT_VOLUME = 50;

    /** Utwór ma pięć i pół minuty, a praca z menadżerem plików trwa dłużej. */
    public const DEFAULT_LOOP = true;

    /**
     * Wzorzec ścieżki: przechodzi wszystko poza znakami sterującymi — dokładnie
     * ten sam, którym `FileInfo` pilnuje argumentów polecenia `file`. Ścieżki nie
     * sprawdzamy tu głębiej: czy plik istnieje i czy da się go odtworzyć,
     * rozstrzyga próba odtworzenia, a nie wzorzec.
     */
    public const TRACK_PATTERN = '/^[^\x00-\x1F\x7F]*$/u';

    public const TRACK_MAX_LENGTH = 255;

    /** @return list<ModuleSetting> */
    public static function declarations(): array
    {
        return [
            ModuleSetting::text(
                self::TRACK,
                'module.' . self::ID . '.setting.' . self::TRACK,
                self::DEFAULT_TRACK,
                self::TRACK_PATTERN,
                self::TRACK_MAX_LENGTH,
            ),
            ModuleSetting::number(
                self::VOLUME,
                'module.' . self::ID . '.setting.' . self::VOLUME,
                self::VOLUME_CHOICES,
                self::DEFAULT_VOLUME,
            ),
            ModuleSetting::toggle(
                self::LOOP,
                'module.' . self::ID . '.setting.' . self::LOOP,
                self::DEFAULT_LOOP,
            ),
        ];
    }

    /** Deklaracja głośności — potrzebna komendzie, żeby sprawdzić wartość tą samą listą. */
    public static function volumeDeclaration(): ModuleSetting
    {
        return self::declarations()[1];
    }

    public static function track(Settings $settings): string
    {
        $value = self::declarations()[0]->valueFrom($settings->moduleValue(self::ID, self::TRACK));

        return is_string($value) && $value !== '' ? $value : self::DEFAULT_TRACK;
    }

    public static function volume(Settings $settings): int
    {
        $value = self::volumeDeclaration()->valueFrom($settings->moduleValue(self::ID, self::VOLUME));

        return is_int($value) ? $value : self::DEFAULT_VOLUME;
    }

    public static function loops(Settings $settings): bool
    {
        $value = self::declarations()[2]->valueFrom($settings->moduleValue(self::ID, self::LOOP));

        return is_bool($value) ? $value : self::DEFAULT_LOOP;
    }
}
