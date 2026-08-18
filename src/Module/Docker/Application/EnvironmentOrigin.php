<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application;

/**
 * Skąd wpis w spisie środowisk się wziął (krok 58, D96 nr 3).
 *
 * **Pochodzenie jest widoczne** — to pierwsza z trzech reguł dwóch źródeł
 * jednej listy: wpis czytany od klienta ma znacznik i nie da się go
 * z aplikacji skasować, bo należy do cudzego narzędzia, a moduł do cudzych
 * plików nie pisze (zdanie z kroków 51 i 52, tu podtrzymane).
 */
enum EnvironmentOrigin: string
{
    /** Wpis własny — z książki modułu w `docker.json`. */
    case Own = 'own';

    /** Kontekst klienta `docker` — czytany, nigdy nie zmieniany. */
    case Client = 'client';

    /** Gniazdo lokalne dopisane przez moduł, gdy klienta `docker` nie ma. */
    case Default = 'default';

    public function labelKey(): string
    {
        return 'module.docker.env.origin.' . $this->value;
    }
}
