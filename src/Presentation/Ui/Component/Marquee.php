<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui\Component;

use LightManager\Application\Ui\FrameText;
use LightManager\Application\Ui\Primitive\Primitive;
use LightManager\Application\Ui\Primitive\TextMark;
use LightManager\Application\Ui\Rect;
use LightManager\Application\Ui\Role;

/**
 * Prostokąt zaznaczony wskaźnikiem, narysowany **tym, co pod nim leży**
 * (krok 56).
 *
 * Rodzeństwo `Highlight`u i tak samo jak on nie jest komponentem z kontraktem,
 * tylko jednym rachunkiem wydzielonym po to, żeby miał jedno miejsce: rysuje go
 * składanie klatki, a mierzy `bin/render-bench`, i obie drogi mają dać co do
 * prymitywu to samo.
 *
 * **Słownik prymitywów zostaje przy tym zamknięty** — zaznaczenie składa się
 * z `TextMark`ów, czyli ósmego kształtu z kroku 30, bo `TextMark` jest dokładnie
 * tym, czego zaznaczenie potrzebuje: napisem na własnym tle. Jest to ten sam
 * chwyt, którym karetka `TextInput` z kroku 19 udała podświetlenie parą
 * istniejących prymitywów. Rola pisma jest jedna dla całego prostokąta
 * (`SelectionText`), więc zaznaczenie **jednolite**: kolor katalogu ani
 * dopasowania filtra nie przebijają się przez nie — tak, jak nie przebijają się
 * w żadnym terminalu.
 *
 * Jeden `TextMark` na wiersz, a nie jeden na komórkę: prostokąt wielkości panelu
 * to wtedy kilkanaście prymitywów zamiast kilku tysięcy, a tor sixelowy składa
 * kilkanaście zapamiętanych bitmap zamiast tyluż tysięcy wywołań.
 */
final class Marquee
{
    /** Pismo w zaznaczeniu — rola, która od kroku 13 znaczy „tekst zaznaczonego wiersza”. */
    private const TEXT = Role::SelectionText;

    /** Tło — trzynasta rola motywu, dołożona w kroku 56 (D100 nr 1). */
    private const GROUND = Role::Marquee;

    private function __construct()
    {
    }

    /**
     * @param FrameText $text warstwa tekstowa klatki **bez** zaznaczenia
     * @param Rect      $area prostokąt do zamalowania
     *
     * @return list<Primitive>
     */
    public static function over(FrameText $text, Rect $area): array
    {
        if ($area->isEmpty()) {
            return [];
        }

        $primitives = [];

        for ($row = max(0, $area->row); $row <= min($text->rows - 1, $area->bottom()); ++$row) {
            $from = max(0, $area->column);
            $to = min($text->columns - 1, $area->right());
            $line = '';

            for ($column = $from; $column <= $to; ++$column) {
                // Spacje na końcu wiersza **zostają** — inaczej niż przy
                // odczycie (`FrameText::textIn()`), bo tu rysujemy blok, a blok
                // z urwanym ogonem przestaje być prostokątem.
                $line .= $text->glyph($row, $column);
            }

            if ($line !== '') {
                $primitives[] = new TextMark($row, $from, $line, self::TEXT, self::GROUND);
            }
        }

        return $primitives;
    }
}
