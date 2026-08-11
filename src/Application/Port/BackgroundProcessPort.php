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
 * **Jedna praca naraz** i jest to decyzja, nie ograniczenie techniczne. Ta sama,
 * którą podjął `ChecksumPort`, i z tego samego powodu: widoczny ekran jest jeden,
 * pasek postępu jest jeden, a druga praca zaczęta bez przerwania pierwszej byłaby
 * dwoma procesami, z których o jednym nikt już nie pamięta. Uchwyt istnieje po to,
 * żeby wyparty zamawiający dowiedział się, że jego praca nie trwa — a nie po to,
 * żeby prac było wiele.
 *
 * **Potomek nie dostaje wejścia.** Port nie ma jak mu go podać i to jest granica
 * postawiona świadomie: proces interaktywny czekałby na to, czego nikt mu nie
 * przyśle, aż do limitu czasu.
 */
interface BackgroundProcessPort
{
    /**
     * Uruchamia polecenie i **nie czeka na nie ani chwili**.
     *
     * Praca trwająca w chwili wywołania zostaje przerwana, bo port prowadzi
     * tylko jedną.
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
     * Wołanie z uchwytem, który przestał być bieżący, oddaje `Idle` — praca,
     * o którą pytasz, już nie trwa i nie ma wyniku.
     */
    public function poll(BackgroundHandle $handle): BackgroundState;

    /**
     * Przerywa pracę i sprząta po niej. Wolno wołać zawsze — także dla uchwytu
     * pracy, która skończyła się sama albo została wyparta.
     */
    public function stop(BackgroundHandle $handle): void;
}
