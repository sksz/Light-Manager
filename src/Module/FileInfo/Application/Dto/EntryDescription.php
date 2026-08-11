<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Dto;

/**
 * Opis zaznaczonego wpisu: nazwa i sekcje, z których składa się jego obraz.
 *
 * Do kroku 18 przypadek użycia oddawał wprost `Popup` z `Domain` — czyli obiekt
 * opisujący **wygląd** okienka. Nie miał do tego prawa: warstwa aplikacji zna
 * treść, a nie kształt, w jakim treść wyląduje na ekranie. Dziś oddaje same
 * dane, a ekran modułu składa z nich sekcje.
 *
 * Klasa mieszkała w `Application/Dto` rdzenia do kroku 20; zeszła do modułu wraz
 * z całą resztą opisu pliku — w rdzeniu nie ma po nim ani jednej klasy.
 *
 * Krok 25 zamienia płaską `list<string>` na sekcje, bo opis przestał być jednym
 * zdaniem od `file` i zrobił się **czterema grupami wierszy**. To, czego tu
 * **nie ma**, jest równie ważne: opis nie niesie ani postępu, ani stanu pracy,
 * która jeszcze trwa. Suma kontrolna liczy się między klatkami, a rzecz
 * zmieniająca się co klatkę nie ma prawa siedzieć w obiekcie liczonym raz na
 * zaznaczenie — dokłada ją ekran, tuż przed narysowaniem.
 */
final class EntryDescription
{
    /** @param list<DescriptionSection> $sections */
    public function __construct(
        public readonly string $name,
        public readonly array $sections,
        /**
         * Rodzaj i rozmiar niesione **osobno**, obok sekcji.
         *
         * Wyglądają na powtórzenie tego, co i tak stoi w wierszach, ale wierszom
         * brakuje jednej rzeczy: są napisami. Ekran musi rozstrzygnąć, czy suma
         * kontrolna w ogóle wolno zacząć — a do tego trzeba liczby i porównania
         * z limitem, nie napisu „412,3 kB”.
         */
        public readonly EntryKind $kind = EntryKind::Unknown,
        public readonly int $sizeInBytes = 0,
    ) {
    }
}
