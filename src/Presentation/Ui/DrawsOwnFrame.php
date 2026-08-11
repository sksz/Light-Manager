<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Rect;

/**
 * Ekran, który sam oprawia swoją strefę środkową.
 *
 * Rdzeń rysuje obwódki stref i to się nie zmienia (reguła kroku 21: „ekran nie
 * rysuje ramek”). Jeden przypadek jednak wyłamuje się z tego podziału i wyłamuje
 * się z powodu, którego rdzeń nie ma jak rozstrzygnąć: **ekran podzielony na dwa
 * panele potrzebuje dwóch obwódek, a rdzeń wie o jednej strefie**. Nie wie też,
 * który panel jest czynny, więc nie ma czym pokazać ogniska.
 *
 * Deklaracja jest osobnym interfejsem, a nie metodą w `ScreenInterface`, i to
 * jest tu istotne: kontrakt ekranu zmieniał się już dwa razy (kroki 18 i 21),
 * a trzecia zmiana zmusiłaby wszystkie sześć ekranów do odpowiadania na pytanie,
 * które dotyczy jednego. Ta sama droga, którą idą `Resettable`, `ReadsContext`,
 * `NeedsTime` i `FocusableInterface`.
 *
 * Odpowiedź jest **metodą, a nie samą deklaracją klasy**, bo bywa zmienna:
 * przeglądarka oprawia się sama tylko wtedy, gdy podział jest włączony i mieści
 * się w oknie. Przy wyłączonym podziale odpowiada „nie” i klatka wygląda co do
 * znaku tak, jak przed krokiem 24.
 */
interface DrawsOwnFrame
{
    /**
     * Oprawa strefy środkowej narysowana przez ekran — albo pusta lista, gdy
     * ekran oprawy nie potrzebuje i zajmuje się nią rdzeń, jak zawsze.
     *
     * **Prymitywy wracają, a nie są rysowane na miejscu**, i to jest tu
     * najważniejsze: rdzeń kładzie je na płaszczyźnie **spodniej**, tej samej,
     * którą renderer pamięta między klatkami (krok 17, dźwignia 4). Obwódka
     * z wygładzanym obrysem kosztuje kilkanaście milisekund, więc narysowana na
     * płaszczyźnie treści powstawałaby trzydzieści razy na sekundę i sama zjadłaby
     * połowę budżetu klatki — zmierzone w kroku 24, zanim ta metoda zaczęła
     * oddawać prymitywy zamiast odpowiedzi „tak/nie”.
     *
     * Pamięć odświeża się sama, bo podpis płaszczyzny obejmuje każdy prymityw:
     * przeniesienie ogniska albo zmiana katalogu zmienia podpis i oprawa powstaje
     * na nowo — raz, a nie w każdej klatce.
     *
     * Prostokąt strefy jest w pytaniu, bo bez niego ekran nie umiałby
     * odpowiedzieć: podział ma próg szerokości, a poniżej progu nie powstaje
     * i wtedy oprawa należy do rdzenia. Rozmiaru strefy ekran sam z siebie nie
     * zna — liczy go `HudLayout`.
     *
     * @return list<Primitive>
     */
    public function ownFrame(Rect $zone): array;
}
