<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Dto\PointerEvent;

/**
 * Ekran, który przyjmuje wskaźnik (krok 55, D95 nr 2).
 *
 * **Trafienie deklaruje ekran, a nie odkrywa rdzeń** — i jest to dokładnie ta
 * sama odpowiedź, której krok 40 udzielił o ognisku (reguła 11p), z dokładnie
 * tego samego powodu: aplikacja nie ma zachowanego drzewa komponentów.
 * Komponent powstaje w `draw()` i ginie razem z klatką (reguła 11a), a wszystko,
 * co przeżywa takt, mieszka **obok** niego — `ScrollWindow`, `SectionState`,
 * `SplitState`, `TreeState`. Pytanie „który element leży pod kursorem” jest więc
 * tak samo niewykonalne, jak było pytanie „który element ma ognisko”.
 *
 * Ekran, który tę zdolność deklaruje, **pamięta prostokąt z ostatniego
 * rysowania** i sam tłumaczy współrzędne na własne pojęcia: numer wiersza listy,
 * stronę podziału, zakładkę. Rdzeń nie zyskuje mapy tego, co gdzie narysowano —
 * i to jest druga, wymierna miara kroku 55.
 *
 * **Zobowiązanie jest obustronne** i pilnuje go
 * `tests/Functional/PointerTruthTest.php`, wzorem `StatusHintsTruthTest`:
 * ekran deklarujący tę zdolność musi obsłużyć kliknięcie w **każde** miejsce,
 * które deklaruje w `focus()`. Inaczej mysz działa w połowie ekranu i nie widać
 * tego, dopóki ktoś nie kliknie.
 */
interface AcceptsPointer
{
    /**
     * Zdarzenie wskaźnika we współrzędnych **siatki znakowej**, liczonych od
     * zera. Zdarzenie spoza prostokątów, które ekran zapamiętał, jest zwykłym
     * przypadkiem, a nie błędem — odpowiedzią jest wtedy `ScreenOutcome::stay()`.
     */
    public function pointer(PointerEvent $event): ScreenOutcome;
}
