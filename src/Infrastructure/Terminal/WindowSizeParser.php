<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

/**
 * Rozumie odpowiedź terminala na zapytanie o rozmiar okna w pikselach
 * (`ESC [ 14 t`), która ma postać `ESC [ 4 ; <wysokość> ; <szerokość> t`.
 *
 * Uwaga na kolejność: terminal podaje najpierw wysokość, a dopiero potem
 * szerokość — odwrotnie, niż podpowiada intuicja.
 */
final class WindowSizeParser
{
    private const RESPONSE_PATTERN = '/\e\[4;(\d+);(\d+)t/';

    public function isComplete(string $buffer): bool
    {
        return preg_match(self::RESPONSE_PATTERN, $buffer) === 1;
    }

    /**
     * Bufor bez pierwszego wystąpienia odpowiedzi. Reszta może należeć do
     * innego zapytania albo być tym, co wpisał użytkownik — nie nam ją kasować.
     */
    public function strip(string $buffer): string
    {
        return preg_replace(self::RESPONSE_PATTERN, '', $buffer, 1) ?? $buffer;
    }

    /** @return array{width: int, height: int}|null */
    public function parse(string $buffer): ?array
    {
        $matches = [];

        if (preg_match(self::RESPONSE_PATTERN, $buffer, $matches) !== 1) {
            return null;
        }

        $height = (int) $matches[1];
        $width = (int) $matches[2];

        if ($width <= 0 || $height <= 0) {
            return null;
        }

        return ['width' => $width, 'height' => $height];
    }
}
