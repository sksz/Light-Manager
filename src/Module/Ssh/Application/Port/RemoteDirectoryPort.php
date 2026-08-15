<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application\Port;

use LightManager\Module\Ssh\Application\RemoteListingState;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;
use LightManager\Module\Ssh\Domain\ValueObject\RemotePath;

/**
 * Odczyt zdalnego katalogu — **praca, nie wynik** (krok 49).
 *
 * Czwarty port projektu o tym kształcie (D46), po sumie kontrolnej (25), pracy
 * tłowej (26) i sesji (48): zaczynanie, które nie czeka ani chwili, posuwanie
 * o takt, stan do obejrzenia, przerywanie. Powód jest ten sam co zawsze i tu
 * najostrzejszy — kawałek pracy trwa tyle, ile trwa **sieć**.
 *
 * **Repozytorium w `Domain` ten moduł nie dostał, choć plan kroku je
 * przewidywał.** Powód jest zasadniczy, a nie oszczędnościowy: repozytorium
 * oddaje agregat **z chwili wywołania**, czyli obiecuje odpowiedź natychmiast —
 * a to jest dokładnie ta obietnica, której cała Faza XVII nie może złożyć.
 * `FilesystemDirectoryRepository` przeglądarki wolno ją składać, bo `scandir()`
 * kosztuje mikrosekundy; tutaj jedno wywołanie to setki milisekund i cudza
 * maszyna po drugiej stronie. Port jest więc jedynym kontraktem odczytu, a jego
 * kształt mówi wprost, że odpowiedź przyjdzie **później**.
 *
 * **Żadna metoda nie rzuca przez granicę** (reguła 8): niepowodzenie wraca
 * kluczem w `RemoteListingState`, bo odczyt nie udaje się rutynowo. Katalog bez
 * prawa wejścia i sesja zerwana w międzyczasie to zwykłe stany, nie awarie.
 *
 * **Jedna praca naraz**, jak wszędzie (11d): wejście w katalog przerywa odczyt
 * poprzedniego. Wynika to zresztą z ceny rozstrzygnięcia D87 nr 9 — pod spodem
 * pracuje rdzeniowy `BackgroundProcessPort`, który jedną pracę naraz **prowadzi
 * z definicji**.
 */
interface RemoteDirectoryPort
{
    /** Stan do obejrzenia w tej klatce. Nigdy nie blokuje i nigdy nie jest `null`. */
    public function state(): RemoteListingState;

    /**
     * Zaczyna odczyt katalogu i **nie czeka na niego ani chwili**.
     *
     * `null` w miejscu ścieżki znaczy **katalog startowy hosta**: profil może
     * wskazywać własny, a gdy milczy — rozstrzyga o nim serwer, i to samo
     * wywołanie pyta go zarazem o listę. Jedno wywołanie, nie dwa, bo otwarcie
     * kanału kosztuje wielokrotnie więcej niż pytanie zadane w jego środku.
     *
     * @param bool $includeHidden czy zamówić także wpisy zaczynające się kropką;
     *                            przełączenie znaczy **nowy obieg**, bo serwer bez
     *                            `ls -a` po prostu ich nie przysyła
     */
    public function begin(HostProfile $host, ?RemotePath $path, bool $includeHidden): void;

    /** Posuwa pracę o jeden takt. **Nigdy nie blokuje.** Bez pracy nie robi nic. */
    public function advance(): void;

    /**
     * Przerywa odczyt i sprząta po nim. Wolno wołać zawsze — także gdy nic nie
     * trwa i gdy pracę wyparł ktoś inny.
     */
    public function stop(): void;
}
