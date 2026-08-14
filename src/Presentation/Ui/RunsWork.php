<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Okno nakładane, które **prowadzi pracę** i kończy się samo (krok 41, D75).
 *
 * Do tego kroku każde okno czekało na klawisz: `OverlayOutcome` wracał wyłącznie
 * z `handle()`, więc okno bez naciśnięcia niczego nie mogło ani zrobić, ani
 * zamknąć. Praca dłuższa od klatki (D46) tego wymaga — pasek postępu, którego
 * nie da się zamknąć bez naciśnięcia klawisza, byłby paskiem kłamiącym po
 * skończonej pracy.
 *
 * Zdolność deklaruje się **osobno**, jak `NeedsTime`, `DrawsOwnFrame`
 * i `DeclaresFocus`: okno, które na nic nie czeka, nie ma czego deklarować,
 * a `OverlayInterface` nie rośnie ani o metodę.
 *
 * Pytanie pada **raz na takt w `GameLoop`**, w fazie „aktualizuj stan” — i to jest
 * różnica wobec pracy kawałkowej z kroku 25, która posuwa się w `draw()`
 * widocznego ekranu. Powód jest jeden i wystarcza: tamta praca **czyta**, ta
 * **zmienia dysk**, a zmiana dysku w środku składania klatki znaczyłaby, że
 * rysowanie ma skutki uboczne.
 */
interface RunsWork
{
    /**
     * Posuwa pracę o jeden kawałek.
     *
     * Skutek jest tym samym `OverlayOutcome`, którym okno odpowiada na klawisz —
     * bo znaczy dokładnie to samo: `stay()` (pracuję dalej), `close($message)`
     * (skończone, oto zdanie do paska stanu) albo `replace($next, $message)`
     * (ustępuję miejsca kolejnemu oknu). `ignored()` znaczenia tu nie ma i nie
     * należy go oddawać — nie ma komu przepuścić klawisza, którego nie było.
     */
    public function advance(): OverlayOutcome;
}
