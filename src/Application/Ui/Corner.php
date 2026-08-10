<?php

declare(strict_types=1);

namespace LightManager\Application\Ui;

/**
 * Stopień zaokrąglenia narożnika — nazwany, bo promień w pikselach jest sprawą
 * renderera.
 *
 * Dwa stopnie, bo tyle ich dziś w klatce jest: panele i okienka zaokrągla
 * `Round` (połowa wysokości wiersza), pasek zaznaczenia — łagodniejsze `Soft`
 * (jedna trzecia). Różnica jest widoczna i celowa, więc musi przetrwać
 * przejście przez port.
 */
enum Corner
{
    case Soft;
    case Round;
}
