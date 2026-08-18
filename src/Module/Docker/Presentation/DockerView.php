<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Presentation;

use LightManager\Module\Docker\Application\DockerSettings;

/**
 * Postać ekranu modułu (krok 51; spis środowisk doszedł w kroku 58).
 *
 * Postacie jednego ekranu, a nie osobne ekrany — rozstrzygnięcie
 * odziedziczone po kroku 49 wraz z jego powodem: `ScreenStack` liczy ekrany po
 * tożsamości, a użytkownik widzi tu **jedno miejsce**, w którym zmienia się
 * treść. Kontrakt ekranu zostaje przez to nietknięty, a moduł nadal kosztuje
 * rdzeń jedną linię.
 */
enum DockerView
{
    case Containers;
    case Images;
    case Logs;
    case Environments;

    public function labelKey(): string
    {
        return 'module.' . DockerSettings::ID . '.view.' . match ($this) {
            self::Containers => 'containers',
            self::Images => 'images',
            self::Logs => 'logs',
            self::Environments => 'environments',
        };
    }
}
