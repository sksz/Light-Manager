<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Dto\PointerEvent;

/**
 * Okno nakładane, które przyjmuje wskaźnik (krok 55).
 *
 * Zdolność jest **drugą obok `AcceptsPointer`** i różni się od niej wyłącznie
 * typem odpowiedzi: okno oddaje `OverlayOutcome`, bo umie rzeczy, których ekran
 * nie umie — zamknąć się, ustąpić miejsca następnemu oknu, wskazać ekran do
 * otwarcia. Jedna wspólna zdolność musiałaby oddawać sumę obu typów, czyli
 * kazać każdemu wołającemu rozstrzygać, który przyszedł. Precedens jest
 * w projekcie od kroku 41: `RunsWork` też jest zdolnością wyłącznie okna.
 *
 * Okno nakładane ma wobec wskaźnika **pierwszeństwo**, tą samą regułą co wobec
 * klawisza (krok 19): kliknięcie **poza** oknem jest połykane, a nie
 * przepuszczane w dół. Okno jest modalne — kliknięcie w listę pod spodem
 * zmieniałoby zaznaczenie, którego użytkownik w tej chwili nie widzi.
 */
interface AcceptsPointerInOverlay
{
    public function pointer(PointerEvent $event): OverlayOutcome;
}
