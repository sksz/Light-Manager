<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Moduł, który pracuje wtedy, gdy **go nie widać** (krok 45, D82).
 *
 * Krok 36 zamknął się zdaniem, że kontrakt modułu cyklu życia nie zna i że
 * rozszerzanie go dla wygody jednego modułu jest niedopuszczalne (D70). To
 * zdanie zostaje tu odwrócone i różnica, na której się to opiera, jest jedna:
 * tam zdolność miała jednego użytkownika **i wyłącznie dla wygody** (muzykę dało
 * się uruchomić komendą, autostart był udogodnieniem), tutaj **bez niej funkcja
 * nie istnieje** — playlista, która nie wie, że utwór się skończył, nie jest
 * playlistą, tylko listą ścieżek.
 *
 * `Presentation\Ui\NeedsTime` nie wystarcza i to zostało sprawdzone, zanim
 * powstał ten plik: o czas klatki pyta `FrameComposer`, a pyta o niego
 * **ekran i okno nakładane**, czyli dokładnie to, co akurat widać. Cała rzecz
 * polega zaś na pracy wtedy, gdy nie widać nic. Stąd druga zdolność, a nie
 * rozszerzenie tamtej; nazwy stoją obok siebie umyślnie, bo różnią się jedną
 * rzeczą: **kto pyta i kiedy**.
 *
 * Zdolność leży w `Application/Module`, jak `ProvidesCommands` — nie wymienia
 * ani jednego typu z `Presentation` (P2).
 *
 * Trzy reguły, bez których takt jest bronią wymierzoną w klatkę. Kto je łamie,
 * psuje **wszystkim** trzydzieści klatek na sekundę, a nie sobie:
 *
 * - **takt jest tani** — porównanie stanu, nigdy wejście-wyjście; praca dłuższa
 *   od klatki podlega D46 i dzieli się na kawałki;
 * - **takt niczego nie wymusza** — nie prosi o przerysowanie i nie zwraca skutku
 *   do pętli (reguła 11b w drugą stronę: pętla i tak rysuje każdą klatkę, więc
 *   wystarczy, że następna narysuje się inaczej);
 * - **takt nie rzuca** — wyjątek modułu nie ma prawa przerwać pętli; łapie go ta
 *   sama droga, którą łapane są wyjątki ekranu (`ModuleTicker`).
 */
interface NeedsTick
{
    /**
     * Jedno uderzenie taktu pętli — raz na klatkę, niezależnie od tego, co jest
     * na wierzchu.
     *
     * @param float $now czas klatki w sekundach, ten sam, który dostaje
     *                   `LoopState::tick()`. Przychodzi **z zewnątrz**, bo zegar
     *                   wołany w środku byłby niemierzalny i niepodstawialny
     *                   w teście (reguła 11b, D28)
     */
    public function tick(float $now): void;
}
