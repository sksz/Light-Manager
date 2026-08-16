<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Co robimy wtyczką compose (krok 51).
 *
 * Trzy czynności, a nie pięć zapowiadane planem, i różnica jest zapisana
 * w dzienniku kroku: `ps` nie wchodzi, bo lista kontenerów **już zna projekt**
 * (etykieta `com.docker.compose.project` przychodzi razem z listą, więc
 * zawężenie do projektu nie kosztuje ani jednego pytania więcej), a `logs -f`
 * nie wchodzi, bo logi kontenera płyną gniazdem i drugi tor do tej samej treści
 * byłby drugą drogą do jednej rzeczy (reguła 11n, w duchu).
 *
 * Wtyczka jest **jedyną częścią modułu idącą procesem potomnym** i nie z wyboru:
 * demon nie wystawia dla compose ani jednego zasobu w API (sprawdzone przy
 * planowaniu, D85).
 */
enum ComposeAction: string
{
    /** Spis projektów uruchomionych na tej maszynie — `compose ls --format json`. */
    case ListProjects = 'ls';

    /** Podniesienie projektu z pliku — `compose -f … up -d`. */
    case Up = 'up';

    /** Położenie projektu — `compose -f … down`. */
    case Down = 'down';

    /**
     * Ile sekund czekać, zanim uznamy pracę za zawieszoną.
     *
     * Spis jest pytaniem lokalnym i ma być szybki. Podniesienie projektu
     * **pobiera obrazy i buduje** — kwadrans nie jest tu przesadą, tylko
     * granicą, po której coś naprawdę stanęło.
     */
    public function timeoutSeconds(): int
    {
        return match ($this) {
            self::ListProjects => 30,
            self::Up => 900,
            self::Down => 300,
        };
    }

    public function labelKey(): string
    {
        return 'module.docker.compose.' . $this->value;
    }
}
