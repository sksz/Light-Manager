<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Port;

use LightManager\Module\Docker\Application\ComposeAction;
use LightManager\Module\Docker\Application\ComposeState;

/**
 * Wtyczka `docker compose` uruchamiana **procesem potomnym** (krok 51).
 *
 * Port jest osobny od `DockerApiPort`, choć obie rzeczy dotyczą Dockera, i osobny
 * **z powodu, którego nie da się obejść**: demon nie wystawia dla compose ani
 * jednego zasobu w API (sprawdzone przy planowaniu, D85). Gniazdo i wiersz
 * poleceń to dwie różne drogi, dwa różne rodzaje niepowodzenia i dwa różne
 * sposoby przerwania — jeden port obsługujący obie kazałby wołającemu pytać,
 * która akurat jest pod spodem.
 *
 * Piąty port projektu o kształcie z D46: zaczynanie, które nie czeka ani chwili,
 * posuwanie o takt, stan do obejrzenia, przerywanie. **Jedna praca compose
 * naraz** — nie z powodu rdzenia (ten od kroku 51 prowadzi kilka), tylko dlatego,
 * że podniesienie i położenie tego samego projektu w tej samej chwili nie ma
 * sensownego wyniku.
 *
 * **Żadna metoda nie rzuca przez granicę** (reguła 8): brak wtyczki, brak pliku
 * i błąd projektu wracają kluczem w `ComposeState`, bo zdarzają się rutynowo.
 */
interface ComposePort
{
    /** Stan do obejrzenia w tej klatce. Nigdy nie blokuje i nigdy nie jest `null`. */
    public function state(): ComposeState;

    /**
     * Zaczyna czynność i **nie czeka na nią ani chwili**.
     *
     * @param ?string $file ścieżka pliku compose; `null` wyłącznie dla `ls`,
     *                      które o żaden plik nie pyta
     */
    public function begin(ComposeAction $action, ?string $file = null): void;

    /** Posuwa pracę o jeden takt. **Nigdy nie blokuje.** Bez pracy nie robi nic. */
    public function advance(): void;

    /**
     * Przerywa pracę i sprząta po niej. Wolno wołać zawsze — także gdy nic nie
     * trwa.
     *
     * Przerwanie ubija **klienta**, a nie to, co on zdążył zrobić: kontenery
     * podniesione do tej chwili zostają podniesione. Tak samo działa `Ctrl`+`C`
     * w terminalu i to jest zachowanie, którego użytkownik się spodziewa.
     */
    public function stop(): void;
}
