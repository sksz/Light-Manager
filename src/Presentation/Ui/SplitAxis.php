<?php

declare(strict_types=1);

namespace LightManager\Presentation\Ui;

/**
 * Oś, wzdłuż której biegnie granica podziału.
 *
 * Nazwa mówi o **granicy**, nie o ułożeniu paneli, i to jest jedyne czytanie,
 * przy którym oba przypadki znaczą to samo w każdym języku: granica pionowa
 * stawia panele obok siebie, pozioma — jeden nad drugim. Napis dla użytkownika
 * mówi za to o panelach („panele obok siebie”), bo tak patrzy na to ktoś, kto
 * nie zna tej klasy.
 */
enum SplitAxis
{
    /** Granica pionowa: panele obok siebie, lewy i prawy. */
    case Vertical;

    /** Granica pozioma: panele jeden nad drugim, górny i dolny. */
    case Horizontal;
}
