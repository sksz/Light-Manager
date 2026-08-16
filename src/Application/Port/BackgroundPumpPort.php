<?php

declare(strict_types=1);

namespace LightManager\Application\Port;

/**
 * Jedna faza pętli głównej: **opróżnij potoki wszystkich prac tłowych** (krok 51).
 *
 * Port jest osobny od `BackgroundProcessPort`, choć obsługuje go ta sama usługa,
 * i osobny jest **konstrukcyjnie, a nie z porządku**: pompowanie należy do pętli,
 * nie do modułu. Ta sama zasada, która w kroku 26 zostawiła `shutdown()` poza
 * portem — „moduł zamawia pracę i ją przerywa, ale o zamykaniu aplikacji nie wie
 * i nie ma prawa wiedzieć” — mówi tutaj, że moduł nie wie i nie ma prawa wiedzieć
 * o klatkach. Różnica wobec tamtego rozwiązania jest jedna: `shutdown()` woła
 * `Bootstrap`, który zna warstwę infrastruktury, a to woła `GameLoop`, który zna
 * wyłącznie porty.
 *
 * Faza istnieje, bo prac jest od tego kroku kilka, a **nieczytany potok
 * zatrzymuje potomka** (reguła z kroku 26, D47). Dopóki praca była jedna, karmił
 * ją jej właściciel przy każdym `poll()`; przy kilku pracach właściciel
 * niezaglądający — bo jego ekran zniknął albo bo ma usterkę — zatrzymałby swojego
 * potomka i nie zauważył tego nikt. Pompowanie z pętli nie zależy od tego, kto
 * akurat patrzy.
 *
 * Faza jest zarazem miejscem, w którym pilnuje się **limitów czasu**: proces
 * przekraczający swój limit ginie w najbliższym takcie, niezależnie od tego, czy
 * ktokolwiek pyta o jego stan.
 */
interface BackgroundPumpPort
{
    /**
     * Posuwa wszystkie prace tłowe o tyle, ile dało się bez czekania: czyta oba
     * potoki każdej z nich, sprawdza, które się skończyły, i ubija te, które
     * przekroczyły swój limit czasu.
     *
     * **Nigdy nie blokuje i nigdy nie rzuca** — stoi w pętli głównej, więc jedna
     * zepsuta praca nie ma prawa zabrać klatki pozostałym. Wołanie bez ani jednej
     * pracy kosztuje jedno sprawdzenie pustej tablicy.
     */
    public function pump(): void;
}
