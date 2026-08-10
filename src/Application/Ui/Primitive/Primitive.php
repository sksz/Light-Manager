<?php

declare(strict_types=1);

namespace LightManager\Application\Ui\Primitive;

/**
 * Kształt gotowy do narysowania — to, co zostaje z komponentu po przekroczeniu
 * portu renderowania.
 *
 * Komponent wie, **jak wyglądać**; prymityw jest tym, co z tej wiedzy zostaje
 * po drugiej stronie granicy. Renderer nie wie, czy rysuje listę plików, pasek
 * stanu czy okno modalne — zna wyłącznie kształty, więc nowy komponent nie
 * kosztuje w nim ani jednej linii.
 *
 * Słownik jest zamknięty i wyznaczony przez to, co klatka rysuje naprawdę
 * (krok 18, D36). Każdy nadmiarowy kształt byłby obowiązkiem dla trybu
 * tekstowego, który musi umieć zdegradować wszystko, czego nie potrafi
 * narysować.
 */
interface Primitive
{
    /**
     * Wszystko, co wpływa na piksele tego kształtu, w jednym napisie.
     *
     * Stąd biorą się klucze pamięci podręcznych renderera: płaszczyzna, która
     * nie zmieniła podpisu, nie zmieniła też ani jednego piksela, więc wolno ją
     * podać z pamięci. Zasada jest ta sama, co przy kluczu wiersza z kroku 17
     * (D34): nie istnieje ścieżka unieważnienia, o której można zapomnieć.
     */
    public function signature(): string;
}
