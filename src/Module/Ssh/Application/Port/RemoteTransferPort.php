<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application\Port;

use LightManager\Application\Dto\TransferChoice;
use LightManager\Module\Ssh\Application\RemoteTransferItem;
use LightManager\Module\Ssh\Application\RemoteTransferState;
use LightManager\Module\Ssh\Application\TransferDirection;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;

/**
 * Przesył plików między tą maszyną a hostem — **praca, nie wynik** (krok 50).
 *
 * Piąty port projektu o tym kształcie (D46) i drugi w tym module, po odczycie
 * katalogu: zaczynanie, które nie czeka ani chwili, posuwanie o takt, stan do
 * obejrzenia, przerywanie. Kształt wzięty z rdzeniowego `FileTransferPort`
 * z kroku 42, bo praca jest tą samą pracą — z trzema różnicami, z których każda
 * wynika z drogi technicznej fazy (D87, D89):
 *
 * 1. **Nie ma etapu liczenia.** Rozmiary przychodzą razem z listą
 *    (`RemoteTransferItem`), więc mianownik paska jest znany od pierwszej klatki.
 * 2. **`advance()` nie bierze budżetu.** Bajtów nie przepisuje PHP, tylko
 *    `sftp` w procesie potomnym, więc takt to jedno `poll()` — dokładnie jak przy
 *    odczycie katalogu. Budżet kawałka mierzony zegarem, który plan kroku
 *    zapowiadał jako główną trudność, nie ma się tu do czego odnieść.
 * 3. **Kolizja rozstrzyga się przed pracą nad plikiem, nie w jej środku.**
 *    Pytanie i tak pada w tym samym miejscu co w kroku 42 — między plikami —
 *    a droga jest tańsza: przy pobieraniu odpowiada dysk (`file_exists`), przy
 *    wysyłaniu **lista, którą panel już ma na ekranie**. Ani jednego obiegu
 *    ponad te, które przenoszą treść.
 *
 * **Odpowiedzi o kolizji bierze się z rdzenia** (`TransferChoice`), a nie
 * powtarza w module. Reguła 15e zabrania powtarzać **mechanizmy** rdzenia;
 * słownik odpowiedzi „nadpisz / pomiń / zmień nazwę / przerwij (i wszystkie)”
 * jest mechanizmem — drugi enum o tych samych sześciu wartościach rozjechałby się
 * z pierwszym przy pierwszej poprawce.
 *
 * **Żadna metoda nie rzuca przez granicę** (reguła 8): niepowodzenie wraca
 * kluczem w stanie. Zerwana sesja, zajęta nazwa i brak prawa zapisu są tu
 * stanami zwykłymi, nie awariami.
 *
 * **Jedna praca naraz** (11d) — i tutaj boli to najmocniej w całym projekcie: pod
 * spodem pracuje rdzeniowy `BackgroundProcessPort`, który prowadzi jedną pracę
 * z definicji (D87 nr 9), a przesył zajmuje go na **cały czas trwania**. W tym
 * czasie panel zdalny się nie odświeży, a `du` w module opisu pliku nie policzy.
 */
interface RemoteTransferPort
{
    /**
     * Zaczyna przesył i **nie czeka na niego ani chwili**.
     *
     * Poprzednia praca, jeśli trwała, zostaje przerwana wraz ze sprzątnięciem
     * połówki.
     *
     * @param list<RemoteTransferItem> $items    źródła wraz z rozmiarami; lista, nie jeden
     *                                           wpis, także wtedy, gdy ma jeden element
     * @param string                   $target   katalog docelowy, ścieżka bezwzględna po
     *                                           stronie przeciwnej do źródła
     * @param list<string>             $occupied nazwy zajęte w katalogu docelowym — czytane
     *                                           **wyłącznie przy wysyłaniu**, bo tam odpowiedź
     *                                           kosztowałaby obieg do serwera, a panel ma ją
     *                                           już na ekranie; przy pobieraniu pyta się dysku
     */
    public function begin(
        HostProfile $host,
        array $items,
        string $target,
        TransferDirection $direction,
        array $occupied = [],
    ): RemoteTransferState;

    /** Posuwa pracę o jeden takt. **Nigdy nie blokuje.** Bez pracy nie robi nic. */
    public function advance(): RemoteTransferState;

    /**
     * Odpowiedź na zajętą nazwę: praca rusza dalej albo staje.
     *
     * Wywołanie poza etapem `Colliding` niczego nie zmienia, a `Rename` bez nazwy
     * też nie — nazwa jest treścią odpowiedzi, nie jej ozdobą.
     *
     * @param ?string $newName sama nazwa, bez ścieżki; wyłącznie dla `TransferChoice::Rename`
     */
    public function resolve(TransferChoice $choice, ?string $newName = null): RemoteTransferState;

    public function state(): RemoteTransferState;

    /**
     * Przerywa pracę i sprząta po niej.
     *
     * Połówka znika **po obu stronach**: lokalną kasuje port rdzenia w tym samym
     * wywołaniu, zdalną — kolejny proces potomny, uruchamiany tutaj i posuwany
     * dalej taktem modułu. Stan mówi „przerwane" **od razu**, nie po kolejnym
     * obiegu sieci: użytkownik przerwał pracę i to jest cała odpowiedź, na którą
     * czeka, a sprzątanie po zdalnej stronie nie jest już jego sprawą.
     *
     * Wolno wołać zawsze — także gdy nic nie trwa.
     */
    public function stop(): void;
}
