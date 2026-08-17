<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * „Co u mnie znaczy »skopiuj to, na czym stoję«” — zdolność deklarowana
 * **osobno**, jak `DeclaresFocus`, `DrawsOwnFrame` i `DragsOwnContent`
 * (krok 57, D101 nr 1).
 *
 * Powód jest ten sam, dla którego ognisko się deklaruje, a trafienie wskaźnika
 * nie odkrywa (reguły 11p i 11z): aplikacja nie ma zachowanego drzewa
 * komponentów (11a), więc „co jest treścią tego miejsca” jest pytaniem, na które
 * odpowiedzieć umie wyłącznie ten, kto miejsce rysuje.
 *
 * **Zdolność jest jedna dla ekranu i dla okna nakładanego** — i to jest różnica
 * wobec pary `AcceptsPointer`/`AcceptsPointerInOverlay`. Tam bliźniak był
 * konieczny, bo różniły się **typy odpowiedzi**: ekran oddaje `ScreenOutcome`,
 * okno `OverlayOutcome`, a okno umie rzeczy, których ekran nie umie. Tu
 * odpowiedź jest **daną**, nie skutkiem zdarzenia — nikt niczego nie zamyka
 * i nie otwiera — więc drugi interfejs niósłby tę samą sygnaturę pod inną nazwą.
 *
 * **Zdolności nie musi deklarować każdy** i to jest zamierzone: rdzeń ma dwa
 * własne źródła, których nie trzeba nikomu zlecać — zaznaczenie z kroku 56
 * i ścieżkę wpisu z `ModuleContext` (krok 49). Ekran milczący nie jest przez to
 * ekranem, w którym kopiowanie nie działa; jest ekranem, w którym kopiowanie nie
 * ma **trzeciego** źródła. Różnica wobec zobowiązania z D99 nr 2 („każde miejsce
 * deklarowane w `focus()` musi dać się kliknąć”) jest właśnie tutaj: tam brak
 * deklaracji znaczył martwą mysz, tu znaczy jedno źródło mniej.
 */
interface CopiesContent
{
    /**
     * Treść wraz ze zdaniem albo `null`, gdy tu i teraz nie ma czego kopiować.
     *
     * Odpowiedź jest **stanem, nie skutkiem**: pada w obsłudze klawisza i niczego
     * nie zmienia. Wołający pyta o nią **po** zaznaczeniu i **przed** ścieżką
     * wpisu, więc `null` nie znaczy „nic nie skopiujesz” — znaczy „u mnie nie
     * ma nic ponad to, co rdzeń wie sam”.
     */
    public function copyable(): ?CopyContent;
}
