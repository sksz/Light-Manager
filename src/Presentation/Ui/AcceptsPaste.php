<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * „Mam pole tekstowe z ogniskiem i przyjmę do niego treść schowka” — zdolność
 * deklarowana osobno, jak `CopiesContent` obok (krok 57, D101 nr 2).
 *
 * **Jest zarazem techniczną gwarancją drugiego z trzech zobowiązań** przyjętych
 * wraz z odblokowaniem `GetSelection` w `bin/run.sh` (D95 nr 5): *odczytana
 * treść ma jedno miejsce docelowe — pole tekstowe z ogniskiem*. Zobowiązanie nie
 * jest tu obietnicą w komentarzu, tylko kształtem kodu: treść schowka wychodzi
 * z parsera wejścia i ma **dokładnie jedną** drogę dalej — przez tę metodę. Nie
 * ma jak trafić do dziennika, do pliku konfiguracyjnego, do procesu potomnego
 * ani do komunikatu w pasku stanu, bo nikt inny jej nie dostaje.
 *
 * Zdolność jest jedna dla ekranu i dla okna nakładanego — z tego samego powodu,
 * co przy `CopiesContent`: odpowiedź nie jest skutkiem zdarzenia, więc bliźniak
 * niósłby tę samą sygnaturę pod inną nazwą.
 *
 * **Deklaracja nie znaczy „zawsze przyjmę”.** Ekran ustawień ma pole tekstowe
 * tylko w trakcie edycji pozycji, a przeglądarka — dopiero po otwarciu okna
 * filtra; dlatego odpowiedzią jest wartość logiczna, a nie `void`. Odmowa
 * znaczy „treść porzucona” i taką właśnie odpowiedź dostaje schowek doręczany do
 * pola, które zdążyło się zamknąć.
 */
interface AcceptsPaste
{
    /**
     * @param string $text treść schowka — dokładnie to, co przyszło
     *
     * @return bool czy treść została przyjęta. `false` znaczy „nie mam gdzie jej
     *              położyć”; wołający **nie próbuje** wtedy nikogo innego —
     *              o wklejenie prosi ten, kto ma ognisko, i nikt poza nim go nie
     *              dostaje
     */
    public function paste(string $text): bool;
}
