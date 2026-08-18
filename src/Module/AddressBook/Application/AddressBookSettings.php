<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;

/**
 * Ustawienia książki adresowej (krok 60, D105 nr 6).
 *
 * **Jedna pozycja i taka ma zostać, dopóki nie ma czego przestawiać więcej.**
 * Rozstrzygnięcie użytkownika odwróciło tu rekomendację planu (brak zakładki):
 * kolejność spisu jest realnym wyborem przy kilkunastu wpisach, bo dopisywanie
 * odkłada nowe na koniec, a szukanie oczami idzie alfabetem.
 */
final class AddressBookSettings
{
    public const ID = 'address-book';

    /** Kolejność spisu: dopisywania albo alfabetycznie. */
    public const ORDER = 'order';

    public const ORDER_ADDED = 'added';

    public const ORDER_ALPHABETICAL = 'alphabetical';

    /** @var list<string> */
    public const ORDER_CHOICES = [self::ORDER_ADDED, self::ORDER_ALPHABETICAL];

    public const DEFAULT_ORDER = self::ORDER_ADDED;

    /** @return list<ModuleSetting> */
    public static function declarations(): array
    {
        return [self::orderDeclaration()];
    }

    /** Czy spis ma iść alfabetem — odczyt tej samej umowy, którą deklaruje `declarations()`. */
    public static function alphabeticalFrom(Settings $settings): bool
    {
        return self::orderDeclaration()->valueFrom($settings->moduleValue(self::ID, self::ORDER))
            === self::ORDER_ALPHABETICAL;
    }

    private static function orderDeclaration(): ModuleSetting
    {
        return ModuleSetting::choice(
            self::ORDER,
            'module.' . self::ID . '.setting.' . self::ORDER,
            self::ORDER_CHOICES,
            self::DEFAULT_ORDER,
        );
    }
}
