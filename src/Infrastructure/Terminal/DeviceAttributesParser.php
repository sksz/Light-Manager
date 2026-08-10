<?php

declare(strict_types=1);

namespace LightManager\Infrastructure\Terminal;

/**
 * Rozumie odpowiedź terminala na zapytanie Primary Device Attributes (DA1).
 *
 * Terminal odpowiada sekwencją `ESC [ ? <lista parametrów> c`, w której każdy
 * parametr to numer możliwości — obecność `4` oznacza obsługę Sixela.
 * Klasa jest czysta (bez I/O), więc daje się testować bez terminala.
 */
final class DeviceAttributesParser
{
    private const SIXEL_PARAMETER = 4;

    private const RESPONSE_PATTERN = '/\e\[\?([0-9;]*)c/';

    /**
     * Odpowiedź może przyjść w kilku porcjach, a w buforze mogą siedzieć obok
     * niej bajty wpisane przez użytkownika — stąd szukanie wzorca w całości,
     * a nie sprawdzanie samego początku.
     */
    public function isComplete(string $buffer): bool
    {
        return preg_match(self::RESPONSE_PATTERN, $buffer) === 1;
    }

    public function supportsSixel(string $buffer): bool
    {
        return in_array(self::SIXEL_PARAMETER, $this->parameters($buffer), true);
    }

    /**
     * Bufor bez pierwszego wystąpienia odpowiedzi DA1. Reszta może należeć do
     * innego zapytania albo być tym, co wpisał użytkownik — nie nam ją kasować.
     */
    public function strip(string $buffer): string
    {
        return preg_replace(self::RESPONSE_PATTERN, '', $buffer, 1) ?? $buffer;
    }

    /** @return list<int> pusta lista, gdy bufor nie zawiera kompletnej odpowiedzi DA1 */
    public function parameters(string $buffer): array
    {
        $matches = [];

        if (preg_match(self::RESPONSE_PATTERN, $buffer, $matches) !== 1) {
            return [];
        }

        if ($matches[1] === '') {
            return [];
        }

        return array_values(array_map(
            static fn (string $parameter): int => (int) $parameter,
            array_filter(
                explode(';', $matches[1]),
                static fn (string $parameter): bool => $parameter !== '',
            ),
        ));
    }
}
