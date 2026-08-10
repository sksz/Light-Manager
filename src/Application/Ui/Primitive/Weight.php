<?php

declare(strict_types=1);

namespace LightManager\Application\Ui\Primitive;

/**
 * Grubość kreski — nazwana, bo w pikselach liczy ją renderer.
 *
 * Trzy przypadki, bo tyle jest w klatce naprawdę: włos rozdzielający komunikat
 * od podpowiedzi w pasku stanu, wyraźniejsza krawędź przy lewym boku paska
 * zaznaczenia i pełne wypełnienie komórek. Kreska cieńsza od komórki jest tu
 * regułą, nie wyjątkiem — pionowa linia o szerokości kolumny wyglądałaby jak
 * blok, a nie jak przegroda.
 */
enum Weight
{
    /** Włos: jeden piksel przy lewej krawędzi prostokąta, z oddechem w pionie. */
    case Hairline;

    /** Krawędź: dwa piksele przy lewej krawędzi, z oddechem w pionie. */
    case Edge;

    /** Pełne komórki prostokąta. */
    case Fill;
}
