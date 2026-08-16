<?php

declare(strict_types=1);

namespace LightManager\Module\Docker\Application\Port;

use LightManager\Module\Docker\Application\PackState;

/**
 * Kontekst budowy spakowany do archiwum — **praca, nie wynik** (krok 51).
 *
 * Szósty port projektu o kształcie z D46. Powód jest tu ten sam, co przy sumie
 * kontrolnej z kroku 25: pakowanie katalogu trwa dłużej niż klatka, a pętla
 * rysuje trzydzieści razy na sekundę.
 *
 * **Wyjątkiem 15b to nie jest**, choć praca dotyka dysku: port **czyta** katalog
 * projektu i pisze wyłącznie do pliku tymczasowego, którego nazwę nadaje sam.
 * Granica wyjątku mówi o zmienianiu tego, co użytkownik widzi — o zmianie nazwy,
 * skasowaniu, przeniesieniu — a nie o każdym bajcie zapisanym na dysku. Tą samą
 * miarą plik konfiguracyjny aplikacji nie potrzebował portu operacji na plikach.
 */
interface BuildContextPort
{
    /** Stan do obejrzenia w tej klatce. Nigdy nie blokuje i nigdy nie jest `null`. */
    public function state(): PackState;

    /**
     * Zaczyna pakowanie katalogu i **nie pakuje w tym wywołaniu ani jednego
     * pliku** — poza spisem tego, co do spakowania.
     */
    public function begin(string $directory): void;

    /** Pakuje kolejny kawałek. **Nigdy nie blokuje** na dłużej niż kawałek. */
    public function advance(): void;

    /** Przerywa pakowanie i **kasuje** archiwum, jeśli zdążyło powstać. */
    public function stop(): void;

    /**
     * Zapomina o gotowym archiwum **bez kasowania go**.
     *
     * Istnieje, bo archiwum ma dwa końce życia i tylko jeden z nich jest
     * przerwaniem: treść wysłana demonowi przestaje być nasza, a plik kasuje ten,
     * kto ją wysłał — **po** tym, jak demon ją przeczyta.
     */
    public function forget(): void;
}
