<?php

declare(strict_types=1);

namespace LightManager\Application\Command;

/**
 * Argumenty gotowe do użycia: nazwa z deklaracji, wartość z wiersza.
 *
 * Komenda nie dostaje ani surowych słów, ani wiersza do rozbioru — dostaje to,
 * co parser rdzenia zdołał sprawdzić. Wartości są napisami także dla argumentów
 * liczbowych; `number()` zamienia je na liczbę, bo sprawdzenie i tak już padło.
 */
final class CommandInput
{
    /** @param array<string, string> $arguments */
    public function __construct(
        private readonly array $arguments = [],
    ) {
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->arguments);
    }

    /** Wartość argumentu; pusty napis, gdy argument nie padł. */
    public function text(string $name): string
    {
        return $this->arguments[$name] ?? '';
    }

    /** Wartość argumentu liczbowego; `$fallback`, gdy argument nie padł. */
    public function number(string $name, int $fallback = 0): int
    {
        $value = $this->arguments[$name] ?? null;

        return $value === null ? $fallback : (int) $value;
    }
}
