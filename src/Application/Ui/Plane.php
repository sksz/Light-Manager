<?php

declare(strict_types=1);

namespace LightManager\Application\Ui;

use LightManager\Application\Ui\Primitive\Primitive;

/**
 * Płaszczyzna klatki — niezależnie umieszczony plan obrazu wraz z porządkiem
 * nakładania.
 *
 * Klatka jest stosem płaszczyzn: spodnia to ekran, nad nią stoi okno modalne,
 * lista rozwijana albo podpowiedź. Do kroku 18 rolę tę pełnił jeden `Popup` —
 * jedyny, zawsze wyśrodkowany i rysowany osobno w obu rendererach.
 *
 * **Płaszczyzna niesie prymitywy, nie komponent.** Komponenty leżą w warstwie
 * dostarczania, a płaszczyzna przekracza port renderowania, więc nie ma prawa
 * trzymać korzenia drzewa — tylko wynik jego narysowania. Drzewo zostaje po
 * stronie ekranu i nigdy nie wychodzi poza `Presentation` (krok 18, D36).
 *
 * Modalność jest tu nieobecna celowo: to reguła **wędrówki klawisza**, a nie
 * rysowania. Renderer nie ma z niej pożytku, więc zostaje po stronie, która
 * rozdaje klawisze.
 *
 * Jest tu za to **nieprzezroczystość** i ta rendererowi jest potrzebna:
 * płaszczyzna oznaczona `opaque` zaczyna od wymazania swojego prostokąta, więc
 * zakrywa to, co pod nią leży. Bez tego okno komend — złożone z `Panel`a, czyli
 * z samej obwódki — przepuszczało miniaturę z pasa podglądu. Flaga jest
 * **opcjonalna z konieczności**: płaszczyzny `chrome` i `content` obejmują całe
 * okno, więc gdyby zakrywały bezwarunkowo, treść wymazywałaby oprawę.
 *
 * Nie ma tu również **przygaszania** tego, co pod spodem, choć plan kroku 18 je
 * przewidywał. Powód jest zmierzony, nie estetyczny: przyciemnienie płótna
 * wypuszcza do klatki kolory spoza motywu, a na tym, że klatka bez bitmapy
 * zawiera **wyłącznie** kolory motywu i ich półcienie, stoi szybka ścieżka
 * palety z kroku 17 (D34). Trzy warianty przygaszania kosztowały 8–34 ms
 * rysowania, ale każdy podnosił kwantyzację z 9 do 84 ms — czyli płacił
 * pięciokrotnie więcej, niż oszczędzał. Okno modalne odróżnia się wypełnieniem
 * i obwódką, i to musi wystarczyć.
 */
final class Plane
{
    /** @param list<Primitive> $primitives */
    public function __construct(
        public readonly string $id,
        public readonly Rect $bounds,
        public readonly array $primitives,
        /** Czy płaszczyzna wymazuje swój prostokąt, zanim się narysuje. */
        public readonly bool $opaque = false,
    ) {
    }

    /**
     * Podpis niosący wszystko, co wpływa na piksele płaszczyzny.
     *
     * To on decyduje, czy renderer może podać ją z pamięci podręcznej zamiast
     * rysować od nowa — a że składa się z podpisów prymitywów, nie istnieje
     * zmiana treści, która by go ominęła.
     */
    public function signature(): string
    {
        $parts = [$this->id, $this->bounds->signature(), $this->opaque ? 'O' : 'T'];

        foreach ($this->primitives as $primitive) {
            $parts[] = $primitive->signature();
        }

        return implode("\x1f", $parts);
    }
}
