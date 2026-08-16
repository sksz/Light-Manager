<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

use LightManager\Application\Dto\BackgroundHandle;
use LightManager\Application\Dto\BackgroundState;

/**
 * Polecenie zewnętrzne uruchomione **obok klatki**, a nie w niej.
 *
 * Kształt portu jest podyktowany tym samym, co kształt `ChecksumPort` w kroku 25:
 * pętla główna rysuje trzydzieści razy na sekundę i nie wolno jej zatrzymać na
 * czas cudzej pracy. Stąd nie ma tu metody `run(command): string` — jest
 * uruchamianie, które nie czeka ani chwili, zaglądanie, które nigdy nie blokuje,
 * i przerywanie.
 *
 * Do kroku 26 jedynym sposobem pracy z procesem było `FileInspectorService`:
 * `proc_open`, a potem sondowanie co 10 ms **w klatce**, aż polecenie skończy.
 * Dla `file` to uchodzi, bo odpowiedź przychodzi w milisekundach. Dla `du` na
 * katalogu domowym nie uchodzi w ogóle — cztery sekundy sondowania to sto
 * dwadzieścia zgubionych klatek.
 *
 * **Kilka prac naraz, każda pod własnym uchwytem** (krok 51, D90 nr 1). Do tego
 * kroku port prowadził **jedną** i była to decyzja z kroku 26, nie ograniczenie
 * techniczne: widoczny ekran jest jeden, pasek postępu jeden, a przy jedynym
 * odbiorcy (`du` w module opisu pliku) nikomu to nie przeszkadzało. Odbiorców
 * jest dziś trzech — doszły moduł sesji zdalnej (kroki 48–50) i moduł Dockera,
 * którego `compose up` trwa minutami, a `compose logs -f` nie kończy się nigdy.
 * Przy dawnej regule podniesienie projektu **ubijało** liczenie zajętości
 * katalogu i odwrotnie, a użytkownik widziałby, że jedna funkcja aplikacji
 * wyłącza drugą bez słowa wyjaśnienia.
 *
 * **Uchwyt zmienił przez to znaczenie, nie kształt.** Do kroku 51 istniał po to,
 * żeby wyparty zamawiający dowiedział się, że jego praca nie trwa; odtąd istnieje
 * po to, żeby prace **dały się rozróżnić**. Odpowiedź `Idle` na uchwyt nieznany
 * portowi zostaje ta sama i znaczy to samo: „ta praca już nie trwa i nie ma
 * wyniku”.
 *
 * **Granica liczby prac jest ustawieniem rdzenia** (`Settings::backgroundJobLimit()`),
 * a jej przekroczenie znaczy **odmowę, nie wyparcie najstarszej** — wyparcie
 * przywracałoby dokładnie tę chorobę, którą rozbudowa leczy. Odmowa idzie drogą,
 * którą port ma od kroku 26: uchwyt wraca zawsze, a powód odbiera pierwszy
 * `poll()`.
 *
 * **Potomek nie dostaje wejścia.** Port nie ma jak mu go podać i to jest granica
 * postawiona świadomie: proces interaktywny czekałby na to, czego nikt mu nie
 * przyśle, aż do limitu czasu.
 *
 * Czego w porcie **nie ma i nie będzie**: sposobu na zakończenie cudzej pracy.
 * Plan kroku 51 zapowiadał tu `stopAll()` „dla sprzątania”, ale metoda dostępna
 * każdemu modułowi pozwalałaby ubić pracę sąsiada jednym wywołaniem — czyli to
 * samo, co dawna reguła „jedna praca naraz”, tyle że na żądanie. Sprzątanie przy
 * wyjściu z aplikacji ma własną drogę **poza portem** od kroku 26
 * (`BackgroundProcessService::shutdown()`, D47), a moduł kończy swoje prace
 * uchwytami, które trzyma (D90).
 */
interface BackgroundProcessPort
{
    /**
     * Uruchamia polecenie i **nie czeka na nie ani chwili**.
     *
     * Prace trwające w chwili wywołania **zostają nietknięte** — to jest cała
     * zmiana kontraktu w kroku 51. Gdy trwa ich już tyle, ile wynosi granica
     * z ustawień, nowa praca **nie powstaje**: uchwyt wraca, a pierwszy `poll()`
     * oddaje `Failed` z powodem `process.tooMany`.
     *
     * Uchwyt wraca **zawsze**, także gdy procesu nie udało się uruchomić —
     * powód niepowodzenia odbiera się wtedy pierwszym `poll()`. Wołający nie
     * musi więc obsługiwać awarii dwiema drogami: jedną przy starcie i drugą
     * w trakcie.
     *
     * @param string $command      gotowy wiersz polecenia; cytowanie argumentów
     *                             należy do wołającego (`escapeshellarg()`)
     * @param int    $timeoutSeconds po ilu sekundach proces zostaje ubity
     */
    public function start(string $command, int $timeoutSeconds): BackgroundHandle;

    /**
     * Zagląda, czy coś się zmieniło. **Nigdy nie blokuje.**
     *
     * Od kroku 51 jest to **czysty odczyt stanu**: potoki opróżnia, procesy
     * sprawdza i limity czasu pilnuje `BackgroundPumpPort::pump()` raz na klatkę,
     * dla wszystkich prac naraz. Rozdzielenie ma jeden powód i jest nim liczba
     * prac: praca, której właściciel przestał zaglądać — bo ekran modułu zniknął
     * albo bo moduł ma usterkę — zatrzymałaby się na pełnym potoku i wisiała aż
     * do limitu czasu, którego też nie miałby kto sprawdzić.
     *
     * Wołanie z uchwytem nieznanym portowi oddaje `Idle` — praca, o którą pytasz,
     * już nie trwa i nie ma wyniku.
     */
    public function poll(BackgroundHandle $handle): BackgroundState;

    /**
     * Przerywa **tę** pracę i sprząta po niej. Wolno wołać zawsze — także dla
     * uchwytu pracy, która skończyła się sama albo której port już nie zna.
     *
     * Cudzych prac nie dotyka i nie ma jak dotknąć: uchwyt jest jedynym sposobem
     * wskazania pracy, a portu nie da się zapytać o cudze uchwyty.
     */
    public function stop(BackgroundHandle $handle): void;
}
