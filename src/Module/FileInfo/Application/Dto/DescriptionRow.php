<?php

declare(strict_types=1);

namespace LightManager\Module\FileInfo\Application\Dto;

/**
 * Jeden wiersz opisu: co po lewej, co po prawej.
 *
 * Po lewej **klucz katalogu**, a nie napis, bo warstwa aplikacji napisów nie
 * składa (reguła 7). Po prawej gotowa wartość — ta akurat jest już napisem,
 * bo pochodzi z systemu (`rwxr-xr-x`, `1000`, wyjście `file`) albo została
 * sformatowana liczbą przez port tłumacza.
 */
final class DescriptionRow
{
    public function __construct(
        public readonly string $labelKey,
        public readonly string $value,
    ) {
    }
}
