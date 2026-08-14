<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Ekran albo okno nakładane, które wie, na czym stoi ognisko.
 *
 * Zdolność deklarowana osobno, jak `NeedsTime`, `Resettable` i `DrawsOwnFrame`,
 * a nie metoda w `ScreenInterface` — bo ekran z jednym miejscem (pomoc, ekran
 * startowy) nie ma czego deklarować i nie powinien być do tego zmuszany. To ta
 * sama reguła, którą krok 24 zapisał dla `DrawsOwnFrame`: kontrakt ekranu nie
 * rośnie za każdym razem, gdy rdzeń chce się o coś zapytać.
 *
 * Odpowiedź jest **liczona co klatkę**, nie zapamiętywana: ognisko przenosi się
 * klawiszem, a pasek stanu ma pokazać nowe miejsce **w tej samej klatce**, w której
 * ono się zmieniło.
 *
 * Zobowiązanie jest przy tym obustronne i pilnuje go
 * `tests/Functional/StatusHintsTruthTest.php`: każde wiązanie oddane w `focus()`
 * musi wystąpić także w `bindings()` (bo okno pomocy zostaje **pełnym** spisem)
 * i musi być naprawdę obsłużone w tym miejscu przez `handle()`. Klawisz pokazany
 * w stopce, a nieobsługiwany tu i teraz, jest błędem — dokładnie tak samo, jak
 * klawisz obsługiwany i przemilczany.
 */
interface DeclaresFocus
{
    /** `null` znaczy „to miejsce nie ma ogniska”, a nie „ognisko jest puste”. */
    public function focus(): ?FocusHint;
}
