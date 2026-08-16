<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

use LightManager\Application\Dto\PointerEvent;
use LightManager\Application\Ui\Rect;

/**
 * Który wiersz listy wskazano (krok 55).
 *
 * Rachunek jest jeden i ten sam w każdym ekranie z listą — przesunięcie okna
 * przewijania plus odległość od pierwszego wiersza treści — więc stoi w jednym
 * miejscu, a nie w dziesięciu. Ta sama decyzja, którą krok 18 podjął dla
 * `ScrollWindow`: trzy kopie tego samego rachunku rozjechały się już przy
 * trzeciej.
 *
 * Klasa **nie jest mapą trafień**: niczego nie pamięta i nie wie, co gdzie
 * narysowano. Prostokąt podaje jej ekran, bo to on rysował.
 */
final class PointerRow
{
    /**
     * @param Rect  $content    prostokąt **treści** listy, już bez obwódki
     * @param int   $offset     przesunięcie okna przewijania
     * @param bool  $withHeader czy pierwszy wiersz prostokąta zajmuje nagłówek kolumn
     * @param ?int  $total      liczba pozycji; podana, odsiewa kliknięcia poniżej
     *                          ostatniej — pod listą krótszą od panelu jest pustka,
     *                          a nie wiersz
     *
     * @return ?int numer pozycji na liście albo `null`, gdy wskaźnik nie trafił
     *              w żaden wiersz treści
     */
    public static function of(
        PointerEvent $event,
        Rect $content,
        int $offset,
        bool $withHeader = false,
        ?int $total = null,
    ): ?int {
        if ($content->isEmpty() || !$event->hits($content)) {
            return null;
        }

        $first = $content->row + ($withHeader ? 1 : 0);

        if ($event->row < $first) {
            return null;
        }

        $index = $offset + ($event->row - $first);

        return $total !== null && $index >= $total ? null : $index;
    }
}
