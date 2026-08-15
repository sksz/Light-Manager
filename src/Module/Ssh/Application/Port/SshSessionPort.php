<?php

declare(strict_types=1);

namespace LightManager\Module\Ssh\Application\Port;

use LightManager\Module\Ssh\Application\SessionState;
use LightManager\Module\Ssh\Domain\ValueObject\HostProfile;

/**
 * Sesja zdalna — **praca, nie wynik** (krok 48, D87 nr 1, 2 i 3).
 *
 * Kształt portu jest podyktowany tym samym, co kształt `BackgroundProcessPort`
 * w kroku 26: pętla rysuje trzydzieści razy na sekundę i nie wolno jej
 * zatrzymać. Nie ma tu więc metody `connect(profile): bool` — jest **zaczynanie,
 * które nie czeka ani chwili**, posuwanie o takt i stan do obejrzenia.
 *
 * **Żadna metoda nie rzuca przez granicę** (reguła 8): niepowodzenie wraca
 * opisem w `SessionState`, bo połączenie nie udaje się rutynowo, a nie
 * wyjątkowo. Host wyłączony nie jest awarią aplikacji.
 *
 * **Jedna sesja naraz** (D87 nr 7) — `connect()` w trakcie innej sesji zrywa
 * poprzednią. Uchwytu, jaki ma `BackgroundProcessPort`, tu nie ma i nie jest
 * potrzebny: tam pracę zamawia dowolny moduł i wyparty zamawiający musiał się
 * o tym dowiedzieć, tutaj zamawiającym jest zawsze ten sam moduł.
 */
interface SshSessionPort
{
    /** Stan do obejrzenia w tej klatce. Nigdy nie blokuje i nigdy nie jest `null`. */
    public function state(): SessionState;

    /**
     * Ustawienia na czas najbliższej pracy — limit czasu i zgoda na dopisanie
     * odcisku nowego hosta.
     *
     * Idą **metodą, a nie konstruktorem**, bo implementacja jest Singletonem,
     * a `AbstractSingleton` konstruktora z parametrami mieć nie może. Idą
     * **portem, a nie klasą konkretną**, bo inaczej wołający musiałby wiedzieć,
     * z którą implementacją rozmawia — czyli po to, żeby atrapa w teście dała
     * się podstawić bez wyjątku od reguły.
     */
    public function useOptions(int $timeoutSeconds, bool $mayRememberHostKeys): void;

    /**
     * Zaczyna łączenie i **nie czeka na nie ani chwili**.
     *
     * Host nieznany `~/.ssh/known_hosts` idzie najpierw po odcisk (`Probing`)
     * i zatrzymuje się na `AwaitingApproval`; host znany rusza wprost
     * w `Connecting`. O tym, którędy poszło, mówi `state()`.
     *
     * @param string|null $password hasło z okna — **wyłącznie** dla profilu
     *                              o sposobie `AuthMethod::Password`. Nie jest
     *                              nigdzie zapisywane i nie przeżywa połączenia
     */
    public function connect(HostProfile $profile, ?string $password = null): void;

    /**
     * Zgoda na nieznany odcisk: łączy dalej, pozwalając klientowi **dopisać
     * wiersz** do `~/.ssh/known_hosts` (`StrictHostKeyChecking=accept-new`).
     *
     * Wolno wołać wyłącznie na etapie `AwaitingApproval`; na każdym innym nie
     * robi nic, bo nie ma czego zatwierdzić.
     */
    public function approve(): void;

    /** Posuwa pracę o jeden takt. **Nigdy nie blokuje.** Bez pracy nie robi nic. */
    public function advance(): void;

    /**
     * Pyta gniazdo mistrza, czy jeszcze żyje — **wyłącznie na żądanie**.
     *
     * Takt tego nie robi i jest to rozstrzygnięcie, nie przeoczenie: pytanie co
     * kilka sekund znaczy proces potomny co kilka sekund, a port tłowy prowadzi
     * jedną pracę naraz (D87 nr 9) — czyli zabijałoby cudzą pracę w kółko. Ceną
     * jest sesja zerwana przez sieć pokazywana jako żywa, dopóki ktoś nie
     * naciśnie `F5`.
     */
    public function refresh(): void;

    /**
     * Zrywa sesję i sprząta po niej — gniazdo mistrza, proces w toku, hasło
     * w pamięci. Wolno wołać zawsze, także gdy nic nie trwa.
     */
    public function disconnect(): void;

    /**
     * Sprzątanie przy wyjściu z aplikacji, **obiema drogami** (D47).
     *
     * Różni się od `disconnect()` tym, że nie zostawia po sobie stanu do
     * pokazania: proces się kończy, więc nie ma komu go oglądać.
     */
    public function shutdown(): void;
}
