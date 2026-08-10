<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

/**
 * Rodzaj wartości argumentu — tyle, ile rdzeń potrafi sprawdzić bez pytania
 * komendy o zdanie.
 *
 * `Path` nie jest sprawdzany na istnienie: rdzeń nie dotyka dysku przy rozbiorze
 * wiersza, a katalog może zniknąć między sprawdzeniem a wejściem do niego.
 * Rodzaj mówi wyłącznie, **czym wartość ma być**; czy da się jej użyć, wie już
 * sama komenda.
 */
enum CommandArgumentKind
{
    case Text;
    case Number;
    case Path;
}
