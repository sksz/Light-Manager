<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Port;

use LightManager\Module\Docker\Application\TunnelState;

/**
 * Tunel gniazda demona przez `ssh -L` (krok 58) — praca, która przeżywa swój
 * uchwyt.
 *
 * `ssh -M -N -f` demonizuje się sam (wzorzec kroku 48), więc uchwyt pracy
 * tłowej gaśnie, a gniazdo zostaje — i to gniazdo jest całym wynikiem tej
 * pracy. Stąd port nie oddaje uchwytu: oddaje **stan o czterech postaciach**
 * (nie ma / wstaje / stoi / nie wstał z powodem), posuwany taktem modułu.
 *
 * **Jeden tunel naraz.** Środowisko bieżące jest jedno, a tunel wstaje
 * wyłącznie na jego wybór (autostartu nie ma — poza zakresem kroku, wzorem
 * kroku 52: start aplikacji nie ma prawa kosztować procesu potomnego).
 */
interface TunnelPort
{
    /**
     * Podnosi tunel dla wpisu. Tunel stojący dla innego wpisu jest wpierw
     * zamykany — razem z jego gniazdem.
     *
     * @param string  $name         nazwa wpisu — wchodzi do nazwy pliku gniazda,
     *                              bo dwa środowiska mają prawo stać jednocześnie
     * @param string  $target       cel dla klienta: `[user@]host`
     * @param int     $port         port SSH
     * @param string  $remoteSocket ścieżka gniazda demona po stronie zdalnej
     * @param ?string $password     hasło do celu — `null` znaczy klucz albo agent
     *                              (`BatchMode`). Droga hasłowa doszła w trakcie
     *                              kroku 58 (D102 nr 4): idzie przez `SSH_ASKPASS`,
     *                              jak w module Ssh, **nigdy wierszem polecenia**,
     *                              i nie jest nigdzie zapisywana
     */
    public function open(string $name, string $target, int $port, string $remoteSocket, ?string $password = null): void;

    /** Posuwa podnoszenie o takt. **Nigdy nie blokuje.** */
    public function advance(): void;

    public function state(): TunnelState;

    /** Zamyka tunel i sprząta gniazdo. Wolno wołać zawsze. */
    public function close(): void;
}
