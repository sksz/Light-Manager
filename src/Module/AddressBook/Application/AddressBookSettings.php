<?php

declare(strict_types=1);

namespace LightManager\Module\AddressBook\Application;

use LightManager\Application\Dto\Settings;
use LightManager\Application\Module\ModuleSetting;

/**
 * Ustawienia książki adresowej (krok 60).
 *
 * **Jedna pozycja i to jest komplet.** Kolejność spisu jest jedyną rzeczą,
 * o której książka rozstrzyga sama — reszta tego, co widać, przychodzi
 * z deklaracji rozdziałów, a te są własnością deklarujących. Pozycji „pytaj
 * przed usunięciem" nie ma świadomie: usunięcie pyta **zawsze**, bo z ekranu
 * książki nie widać, kto się na wpis powołuje.
 */
final class AddressBookSettings
{
    public const ID = 'address-book';

    /** Porządek spisu: kolejność dopisywania albo alfabetycznie po nazwie. */
    public const ORDER = 'order';

    public const ORDER_ADDED = 'added';

    public const ORDER_NAME = 'name';

    private const DEFAULT_ORDER = self::ORDER_ADDED;

    /** @return list<ModuleSetting> */
    public static function declarations(): array
    {
        return [
            ModuleSetting::choice(
                self::ORDER,
                'module.' . self::ID . '.setting.' . self::ORDER,
                [self::ORDER_ADDED, self::ORDER_NAME],
                self::DEFAULT_ORDER,
            ),
        ];
    }

    /** Czy spis ma iść alfabetycznie; `false` znaczy kolejność dopisywania. */
    public static function sortsByName(Settings $settings): bool
    {
        return $settings->moduleValue(self::ID, self::ORDER) === self::ORDER_NAME;
    }

    /** Klucz katalogu napisów tego modułu — skrót używany w całym module. */
    public static function key(string $suffix): string
    {
        return 'module.' . self::ID . '.' . $suffix;
    }
}
