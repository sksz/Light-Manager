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
 *
 * Krok 25 dokłada cztery kolejne. **Nie ma wśród nich `du` ani limitu czasu pracy
 * tłowej**, które przewidywał plan (P7): zajętość na dysku wymaga procesu
 * potomnego doglądanego między klatkami, a ten mechanizm dostał własny krok (26).
 * Pozycja, która nie ma czym sterować, byłaby obietnicą bez pokrycia.
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

    /** Czas bezwzględny czy „ile temu” — zapis, nie strefa. */
    public const TIME_FORMAT = 'timeFormat';

    public const TIME_FORMAT_ABSOLUTE = 'absolute';

    public const TIME_FORMAT_RELATIVE = 'relative';

    public const TIME_FORMAT_CHOICES = [self::TIME_FORMAT_ABSOLUTE, self::TIME_FORMAT_RELATIVE];

    /** I-węzeł i liczba dowiązań — dla większości plików szum, dla nielicznych sedno. */
    public const INODE = 'inode';

    public const DEFAULT_INODE = false;

    /** Suma kontrolna — **domyślnie wyłączona**, bo czyta cały plik. */
    public const CHECKSUM = 'checksum';

    public const DEFAULT_CHECKSUM = false;

    /** Powyżej tylu MiB suma kontrolna nie startuje i mówi dlaczego. */
    public const CHECKSUM_LIMIT = 'checksumLimit';

    public const CHECKSUM_LIMIT_CHOICES = [16, 64, 256, 1024];

    public const DEFAULT_CHECKSUM_LIMIT = 256;

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
            ModuleSetting::choice(
                self::TIME_FORMAT,
                'module.' . self::ID . '.setting.' . self::TIME_FORMAT,
                self::TIME_FORMAT_CHOICES,
                self::TIME_FORMAT_ABSOLUTE,
            ),
            ModuleSetting::toggle(
                self::INODE,
                'module.' . self::ID . '.setting.' . self::INODE,
                self::DEFAULT_INODE,
            ),
            ModuleSetting::toggle(
                self::CHECKSUM,
                'module.' . self::ID . '.setting.' . self::CHECKSUM,
                self::DEFAULT_CHECKSUM,
            ),
            ModuleSetting::number(
                self::CHECKSUM_LIMIT,
                'module.' . self::ID . '.setting.' . self::CHECKSUM_LIMIT,
                self::CHECKSUM_LIMIT_CHOICES,
                self::DEFAULT_CHECKSUM_LIMIT,
            ),
        ];
    }

    /** Czy czasy pokazujemy jako „ile temu”, a nie datą. */
    public static function relativeTime(Settings $settings): bool
    {
        $value = self::declarations()[2]->valueFrom($settings->moduleValue(self::ID, self::TIME_FORMAT));

        return $value === self::TIME_FORMAT_RELATIVE;
    }

    public static function inode(Settings $settings): bool
    {
        $value = self::declarations()[3]->valueFrom($settings->moduleValue(self::ID, self::INODE));

        return is_bool($value) ? $value : self::DEFAULT_INODE;
    }

    public static function checksum(Settings $settings): bool
    {
        $value = self::declarations()[4]->valueFrom($settings->moduleValue(self::ID, self::CHECKSUM));

        return is_bool($value) ? $value : self::DEFAULT_CHECKSUM;
    }

    /** Limit rozmiaru w bajtach — pozycja ustawień mówi w MiB, kod liczy w bajtach. */
    public static function checksumLimitBytes(Settings $settings): int
    {
        $value = self::declarations()[5]->valueFrom($settings->moduleValue(self::ID, self::CHECKSUM_LIMIT));

        return (is_int($value) ? $value : self::DEFAULT_CHECKSUM_LIMIT) * 1024 * 1024;
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
