<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;

/**
 * Ustawienia modułu w jednym miejscu: deklaracja pozycji i odczyt wartości.
 *
 * Deklaracja i odczyt stoją obok siebie, bo są dwiema stronami tej samej umowy —
 * rozdzielone rozjeżdżałyby się przy pierwszej zmianie listy wartości. Zakładkę
 * składa z nich klasa modułu, a wartości czyta narzędzie opisujące plik.
 *
 * Dwie pozycje, dwa nowe rodzaje (P14): liczba z listy i pole tekstowe. Jeden
 * moduł przeciera obie ścieżki, których krok 20 dokłada do ekranu ustawień.
 */
final class FileInfoSettings
{
    public const ID = 'file-info';

    public const TIMEOUT = 'timeout';

    public const ARGUMENTS = 'arguments';

    /** Sekundy, po których polecenie `file` zostaje przerwane. */
    public const TIMEOUT_CHOICES = [1, 2, 5, 10];

    public const DEFAULT_TIMEOUT = 2;

    /**
     * Wzorzec dodatkowych argumentów: przechodzi wszystko poza znakami
     * sterującymi.
     *
     * Białej listy znaków tu **nie ma** i jest to decyzja, nie przeoczenie.
     * Argumenty rozbiera na słowa ten sam parser, co wiersz komend (cudzysłowy
     * proste i podwójne grupują), a każde słowo idzie przez `escapeshellarg()`
     * przed doklejeniem do polecenia — znak specjalny nie ma więc jak zmienić
     * jednego polecenia w dwa. Wzorzec zamyka za to drogę znakom, których
     * escapowanie nie ratuje: bajtowi zerowemu i znakowi nowej linii.
     */
    public const ARGUMENTS_PATTERN = '/^[^\x00-\x1F\x7F]*$/u';

    public const ARGUMENTS_MAX_LENGTH = 64;

    /** @return list<ModuleSetting> */
    public static function declarations(): array
    {
        return [
            ModuleSetting::number(
                self::TIMEOUT,
                'module.' . self::ID . '.setting.' . self::TIMEOUT,
                self::TIMEOUT_CHOICES,
                self::DEFAULT_TIMEOUT,
            ),
            ModuleSetting::text(
                self::ARGUMENTS,
                'module.' . self::ID . '.setting.' . self::ARGUMENTS,
                pattern: self::ARGUMENTS_PATTERN,
                maxLength: self::ARGUMENTS_MAX_LENGTH,
            ),
        ];
    }

    public static function timeout(Settings $settings): int
    {
        $value = self::declarations()[0]->valueFrom($settings->moduleValue(self::ID, self::TIMEOUT));

        return is_int($value) ? $value : self::DEFAULT_TIMEOUT;
    }

    public static function arguments(Settings $settings): string
    {
        $value = self::declarations()[1]->valueFrom($settings->moduleValue(self::ID, self::ARGUMENTS));

        return is_string($value) ? $value : '';
    }
}
