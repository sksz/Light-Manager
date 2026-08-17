<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Ekran, który **sam prowadzi przeciągnięcie** — i przez to zabiera je
 * zaznaczaniu treści (krok 56, D100 rozstrzygnięcie 2).
 *
 * Deklaruje się osobno, jak `DeclaresFocus`, `DrawsOwnFrame`, `NeedsTime`
 * i `RunsWork`, i z tego samego powodu: ekran, który niczego nie przeciąga, ma
 * o tym **milczeć**, a nie odpowiadać „nie”.
 *
 * Pytanie pada w jednym miejscu (`InputHandler`) i wyłącznie przy
 * przeciągnięciu. Bez niego rdzeń nie ma jak odróżnić dwóch rzeczy, które
 * z zewnątrz wyglądają identycznie: przeciągnięcia **granicy podziału** (krok
 * 55, `SplitState`) od przeciągnięcia zaznaczającego treść. Obie zaczynają się
 * naciśnięciem lewego przycisku i obie idą tą samą kolejką zdarzeń; różni je
 * wyłącznie to, co leżało pod pierwszym naciśnięciem — a tego rdzeń nie wie
 * i wiedzieć nie ma prawa, bo mapy trafień nie prowadzi (reguła 11z).
 *
 * Odpowiedź jest **stanem, nie skutkiem zdarzenia**, i to jest tu zaletą:
 * granicę chwyta się naciśnięciem, więc zanim przyjdzie pierwsze przeciągnięcie,
 * ekran już wie, że ją trzyma. Kontrakt `ScreenOutcome` zostaje przez to
 * nietknięty — a jest to typ, który wraca także z `handle()`, gdzie znacznik
 * „zużyte” nie znaczyłby nic.
 */
interface DragsOwnContent
{
    /** Czy ekran jest w trakcie **własnego** przeciągnięcia. */
    public function isDraggingOwn(): bool;
}
