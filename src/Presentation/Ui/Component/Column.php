<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\Role;
use LightManager\Presentation\Ui\Container\Span;

/**
 * Kolumna tabeli: **czego chce**, a nie ile dostanie.
 *
 * Rozdział szerokości należy do tabeli, bo tylko ona zna prostokąt i wszystkie
 * kolumny naraz. Kolumna mówi wyłącznie trzy rzeczy: ile miejsca by chciała, ile
 * jej wystarczy i jak chętnie ustępuje — czyli dokładnie to, co `Span` niesie od
 * kroku 27 dla obu osi.
 *
 * **Etykieta jest już przetłumaczona.** Komponent nie sięga po katalog napisów
 * i nie zna kluczy; tłumaczy ten, kto kolumnę tworzy — tak samo, jak przy
 * `ListRow` i `Panel` od kroku 18.
 *
 * Rola jest opcjonalna i to jest jej cały sens: `null` znaczy „rola całego
 * wiersza”, a wartość — „ta kolumna mówi własnym głosem”. Stąd bierze się
 * wyszarzony rozmiar obok nazwy w pełnym kolorze, bez podwajania liczby wierszy
 * w liście.
 */
final readonly class Column
{
    public function __construct(
        public Span $span,
        public Align $align = Align::Left,
        /** Nagłówek kolumny — **już przetłumaczony**; pusty, gdy nagłówków nie ma. */
        public string $label = '',
        /** Rola koloru treści; `null` — rola wiersza. */
        public ?Role $role = null,
    ) {
    }

    /**
     * Kolumna o stałej szerokości — data, prawa, rozmiar.
     *
     * `yieldOrder` mówi, jak chętnie kolumna znika w wąskim oknie: im mniejszy,
     * tym wcześniej. Kolumna, która zeszłaby poniżej swojej szerokości, **znika
     * w całości** — przycięta data („202…”) jest gorsza niż jej brak.
     */
    public static function fixed(
        int $width,
        int $yieldOrder = 0,
        Align $align = Align::Left,
        string $label = '',
        ?Role $role = null,
    ): self {
        return new self(Span::rigid($width, $yieldOrder), $align, $label, $role);
    }

    /**
     * Kolumna biorąca resztę miejsca — w liście plików jest nią nazwa.
     *
     * Elastyczna kolumna **nigdy nie ustępuje** (`Span::flexible()` daje jej
     * najwyższą możliwą kolejność), więc nazwa nie ma jak zniknąć. To nie jest
     * przypadek: lista bez nazw nie jest listą.
     *
     * **Minimum jest tu ważniejsze, niż wygląda, i nie jest kosmetyką.** To ono
     * rozstrzyga, kiedy kolumny stałe zaczynają ustępować: dopóki suma minimów
     * mieści się w prostokącie, nikt nie ustępuje, a elastyczna dostaje tylko
     * resztę. Minimum równe czterem znaczy więc „nazwa może zejść do czterech
     * znaków, byle data została” — czyli odwrotność tego, czego chce lista
     * plików. Wołający ma podać szerokość, poniżej której **jego** treść
     * przestaje mieć sens.
     */
    public static function flexible(
        int $minimum = 4,
        Align $align = Align::Left,
        string $label = '',
        ?Role $role = null,
    ): self {
        return new self(Span::flexible($minimum), $align, $label, $role);
    }
}
