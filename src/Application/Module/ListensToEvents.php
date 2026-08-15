<?php

declare(strict_types=1);

namespace LightManager\Application\Module;

/**
 * Moduł, który chce wiedzieć, że w aplikacji coś się stało (krok 46, D83).
 *
 * Zdolność deklaruje się osobno, jak `ProvidesCommands` i `NeedsTick`, i leży
 * w `Application/Module`, bo nie wymienia ani jednego typu z `Presentation` (P2).
 * Warunek, pod którym wolno ją dopisać, jest ten sam, którym krok 45 uzasadnił
 * takt: zdolność wchodzi wtedy, gdy **bez niej funkcja nie istnieje** — efekt
 * dźwiękowy bez zdarzenia nie ma czego zagrać.
 *
 * Trzy reguły odbioru, bez których publikacja byłaby bronią wymierzoną w pętlę:
 *
 * - **odbiór nie rzuca** — wyjątek odbiorcy nie ma prawa przerwać ani pętli, ani
 *   czynności, w środku której padło zdarzenie; łapie go `EventRegistry`, tą samą
 *   drogą, którą `ModuleTicker` łapie wyjątki taktu;
 * - **odbiór nie oddaje odpowiedzi** — to nie jest droga, którą moduł zmienia bieg
 *   aplikacji; od tego są komendy;
 * - **odbiór nie publikuje** — zdarzenie odebrane nie ma prawa wywołać kolejnego
 *   (rejestr i tak na to nie pozwoli), bo słownik zamknięty przestałby być
 *   zamknięty w tej samej chwili, w której zaczęłyby się łańcuchy.
 *
 * Czasu tu nie ma i to jest świadome: kontrakt zostaje jednoargumentowy, a
 * odbiorca, któremu czas jest potrzebny (odtwarzacz efektów pilnuje minimalnego
 * odstępu), bierze go z `NeedsTick` — zdolności, którą i tak już ma. Dwie drogi do
 * zegara byłyby dwiema prawdami o tym, która jest teraz klatka.
 */
interface ListensToEvents
{
    /**
     * @param string $event nazwa zdarzenia ze słownika (`AppEvent` albo deklaracja
     *                      modułu); odbiorca, który jej nie zna, ma **milczeć**,
     *                      a nie rozgałęziać się na wszystkie przypadki
     */
    public function onEvent(string $event): void;
}
